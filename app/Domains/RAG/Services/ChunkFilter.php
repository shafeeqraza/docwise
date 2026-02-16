<?php

namespace App\Domains\RAG\Services;

use App\Domains\RAG\DTOs\ChunkDTO;
use App\Services\V1\Common\LogService;

/**
 * Service for filtering chunks by relevance.
 *
 * Follows Single Responsibility Principle (SRP): Only responsible for
 * filtering chunks based on similarity thresholds and business rules.
 */
class ChunkFilter
{
    public function __construct(
        private readonly ?LogService $logService = null
    ) {}

    /**
     * Filter chunks by similarity threshold to ensure only relevant chunks are used.
     *
     * @param array<ChunkDTO> $chunks
     * @param float $similarityThreshold Minimum similarity score (0.0-1.0)
     * @return array<ChunkDTO>
     */
    public function filterRelevant(array $chunks, float $similarityThreshold = 0.7): array
    {
        if (empty($chunks)) {
            return [];
        }

        $relevantChunks = [];
        foreach ($chunks as $chunk) {
            $similarityScore = $chunk->metadata['similarity_score'] ?? 0.0;

            // If similarity score meets threshold, include the chunk
            if ($similarityScore >= $similarityThreshold) {
                $relevantChunks[] = $chunk;
            }
        }

        // If no chunks meet the threshold but we have chunks, check if highest score is reasonable
        // This handles cases where threshold might be too strict but we still want some results
        if (empty($relevantChunks) && !empty($chunks)) {
            $maxScore = $this->getMaxSimilarityScore($chunks);

            // If highest score is still reasonably high (>= 0.5), use it
            // Otherwise, return empty (no relevant context)
            if ($maxScore >= 0.5) {
                $this->logService?->warning('No chunks met strict threshold, using lower threshold', [
                    'strict_threshold' => $similarityThreshold,
                    'highest_score' => $maxScore,
                    'chunks_used' => count($chunks),
                ]);
                return $chunks; // Use all chunks if highest is reasonable
            }
        }

        return $relevantChunks;
    }

    /**
     * Get the maximum similarity score from chunks.
     *
     * @param array<ChunkDTO> $chunks
     * @return float
     */
    private function getMaxSimilarityScore(array $chunks): float
    {
        return max(array_map(function (ChunkDTO $chunk) {
            return $chunk->metadata['similarity_score'] ?? 0.0;
        }, $chunks));
    }
}
