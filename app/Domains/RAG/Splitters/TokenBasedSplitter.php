<?php

namespace App\Domains\RAG\Splitters;

use App\Domains\RAG\Tokenizers\TokenizerInterface;

/**
 * Handles token-based text splitting.
 *
 * Follows Single Responsibility Principle (SRP): Only token-based splitting logic.
 */
class TokenBasedSplitter
{
    /**
     * Create a new TokenBasedSplitter instance.
     *
     * @param TokenizerInterface $tokenizer Tokenizer for token-based operations
     * @param int $chunkSize Maximum chunk size in tokens
     */
    public function __construct(
        private TokenizerInterface $tokenizer,
        private int $chunkSize
    ) {}

    /**
     * Split text by tokens using binary search for optimal chunk boundaries.
     *
     * @param string $text The text to split
     * @return array Array of token-based chunks
     */
    public function splitByTokens(string $text): array
    {
        $chunks = [];
        $textLength = mb_strlen($text);
        $position = 0;

        // Binary search approach to find optimal split points
        while ($position < $textLength) {
            $remainingText = mb_substr($text, $position);
            $chunk = $this->findOptimalChunk($remainingText, $this->chunkSize);
            $chunkLength = mb_strlen($chunk);
            $chunks[] = $chunk;
            $position += $chunkLength;
        }

        return $chunks;
    }

    /**
     * Find optimal chunk size by binary search on token count.
     * Uses a predictive approach to minimize tokenizer calls for API-based tokenizers.
     *
     * @param string $text The text to chunk
     * @param int $maxTokens Maximum tokens for the chunk
     * @return string The chunk text
     */
    private function findOptimalChunk(string $text, int $maxTokens): string
    {
        $textLength = mb_strlen($text);
        if ($textLength === 0) return '';

        $low = 0;
        $high = $textLength;
        $bestChunk = '';

        // Initial estimate based on average 4 chars per token
        $estimatedChars = min($maxTokens * 4, $textLength);
        $currentPos = $estimatedChars;

        $iterations = 0;
        $maxIterations = 10; // Safety cap

        // Binary search to find the largest chunk that fits within token limit
        while ($low < $high && $iterations < $maxIterations) {
            $iterations++;
            $chunk = mb_substr($text, 0, $currentPos);
            $tokenCount = $this->tokenizer->getTokenLength($chunk);

            if ($tokenCount <= $maxTokens) {
                $bestChunk = $chunk;
                $low = $currentPos + 1;

                // If we are very close to maxTokens, we might be done
                if ($tokenCount > $maxTokens * 0.95) {
                    // Try to nudge forward slightly to see if we can get closer
                    $currentPos = (int) min($currentPos + ($maxTokens - $tokenCount) * 4, $high);
                } else {
                    $currentPos = (int) (($currentPos + $high) / 2);
                }
            } else {
                $high = $currentPos;
                // Predictive jump back
                $overage = $tokenCount - $maxTokens;
                $currentPos = (int) max($low, $currentPos - ($overage * 3)); // 3 chars/token conservative
            }

            // Fallback to standard binary search if predictive jump is out of bounds
            if ($currentPos <= $low || $currentPos >= $high) {
                $currentPos = (int) (($low + $high) / 2);
            }
        }

        // If we still have search space but reached iteration cap, do one last binary split
        if ($low < $high && $iterations >= $maxIterations) {
            $mid = (int) (($low + $high) / 2);
            $chunk = mb_substr($text, 0, $mid);
            if ($this->tokenizer->getTokenLength($chunk) <= $maxTokens) {
                $bestChunk = $chunk;
            }
        }

        // If we couldn't find a good split, try to break at word boundary
        if (empty($bestChunk) || mb_strlen($bestChunk) < $textLength * 0.5) {
            // Try to find a word boundary near the target
            // Estimate character length based on average tokens per character
            $targetLength = (int) ($maxTokens * 3.5); // Rough approximation
            $chunk = mb_substr($text, 0, min($targetLength, $textLength));
            $lastSpace = mb_strrpos($chunk, ' ');
            if ($lastSpace !== false && $lastSpace > $targetLength * 0.7) {
                $chunk = mb_substr($text, 0, $lastSpace);
            }
            return $chunk;
        }

        return $bestChunk;
    }

    /**
     * Update chunk size.
     *
     * @param int $chunkSize
     * @return self
     */
    public function setChunkSize(int $chunkSize): self
    {
        $this->chunkSize = $chunkSize;
        return $this;
    }

    /**
     * Update tokenizer.
     *
     * @param TokenizerInterface $tokenizer
     * @return self
     */
    public function setTokenizer(TokenizerInterface $tokenizer): self
    {
        $this->tokenizer = $tokenizer;
        return $this;
    }
}
