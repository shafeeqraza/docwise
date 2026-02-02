<?php

namespace App\Domains\RAG\Tokenizers\Concerns;

/**
 * Trait for batch token counting with concurrency control.
 *
 * Single Responsibility: Handles batch processing logic including:
 * - Empty text filtering
 * - Chunking into manageable batches
 * - Result ordering preservation
 *
 * Requires implementing class to provide:
 * - countTokens(string $text): int
 * - processBatch(array $texts): array
 */
trait BatchesTokenCounting
{
    /**
     * Maximum concurrent requests per batch.
     * Override in implementing class if needed.
     */
    protected function getMaxConcurrentRequests(): int
    {
        return 10;
    }

    /**
     * Count tokens for multiple texts with batching support.
     *
     * @param array<string> $texts Array of texts to count tokens for
     * @return array<int> Array of token counts (same order as input)
     */
    public function countTokensBatch(array $texts): array
    {
        // Handle edge cases
        $edgeCaseResult = $this->handleEdgeCases($texts);
        if ($edgeCaseResult !== null) {
            return $edgeCaseResult;
        }

        // Separate empty and non-empty texts
        [$emptyIndices, $nonEmptyTexts] = $this->partitionTexts($texts);

        // If all texts are empty, return zeros
        if (empty($nonEmptyTexts)) {
            return array_fill(0, count($texts), 0);
        }

        // Process in batches and merge results
        $nonEmptyResults = $this->processInBatches($nonEmptyTexts);

        return $this->mergeResults($texts, $emptyIndices, $nonEmptyResults);
    }

    /**
     * Handle edge cases for batch processing.
     *
     * @param array<string> $texts
     * @return array<int>|null Returns result array for edge cases, null otherwise
     */
    protected function handleEdgeCases(array $texts): ?array
    {
        if (empty($texts)) {
            return [];
        }

        if (count($texts) === 1) {
            return [$this->countTokens(reset($texts))];
        }

        return null;
    }

    /**
     * Partition texts into empty and non-empty groups.
     *
     * @param array<string> $texts
     * @return array{0: array<int, int>, 1: array<int, string>}
     */
    protected function partitionTexts(array $texts): array
    {
        $emptyIndices = [];
        $nonEmptyTexts = [];

        foreach ($texts as $index => $text) {
            if (empty(trim($text))) {
                $emptyIndices[$index] = 0;
            } else {
                $nonEmptyTexts[$index] = $text;
            }
        }

        return [$emptyIndices, $nonEmptyTexts];
    }

    /**
     * Process texts in batches with concurrency limit.
     *
     * @param array<int, string> $textsWithIndices
     * @return array<int, int>
     */
    protected function processInBatches(array $textsWithIndices): array
    {
        $results = [];
        $batches = array_chunk($textsWithIndices, $this->getMaxConcurrentRequests(), true);

        foreach ($batches as $batch) {
            $batchResults = $this->processBatch($batch);
            $results = array_replace($results, $batchResults);
        }

        return $results;
    }

    /**
     * Merge empty and non-empty results maintaining original order.
     *
     * @param array<string> $originalTexts
     * @param array<int, int> $emptyIndices
     * @param array<int, int> $nonEmptyResults
     * @return array<int>
     */
    protected function mergeResults(array $originalTexts, array $emptyIndices, array $nonEmptyResults): array
    {
        $results = [];

        foreach ($originalTexts as $index => $text) {
            $results[] = $emptyIndices[$index] ?? $nonEmptyResults[$index];
        }

        return $results;
    }

    /**
     * Process a single batch of texts.
     * Must be implemented by the using class.
     *
     * @param array<int, string> $batch
     * @return array<int, int>
     */
    abstract protected function processBatch(array $batch): array;
}
