<?php

namespace App\Domains\RAG\Services;

use App\Domains\RAG\Contracts\EmbeddingProvider;
use App\Domains\RAG\DTOs\EmbeddingDTO;
use App\Services\V1\Common\LogService;

/**
 * Service for generating embeddings for document chunks.
 *
 * Follows Adapter Pattern: Adapts domain embedding providers to application layer needs.
 * Follows Single Responsibility Principle (SRP): Only embedding generation logic.
 */
class EmbeddingService
{
    public function __construct(
        private EmbeddingProvider $embeddingProvider,
        private LogService $logService
    ) {}

    /**
     * Generate embedding for a single text.
     *
     * @param string $text The text to generate embedding for
     * @param string $model The embedding model to use
     * @return EmbeddingDTO The embedding DTO
     */
    public function generateEmbedding(string $text, string $model): EmbeddingDTO
    {
        return $this->embeddingProvider->generateEmbedding($text, $model);
    }

    /**
     * Generate embeddings for multiple chunks in batch.
     *
     * @param array $chunks Array of DocumentChunk models
     * @param string $model The embedding model to use
     * @return array Array of chunks with embeddings
     */
    public function generateEmbeddingsBatch(array $chunks, string $model): array
    {
        if (empty($chunks)) {
            return [];
        }

        $results = [];

        // Extract texts from chunks
        $texts = array_map(fn($chunk) => $chunk->content, $chunks);

        try {
            // Generate embeddings for all texts using the provider
            $embeddings = $this->embeddingProvider->generateEmbeddingsBatch($texts, $model);

            // Map embeddings to chunks
            foreach ($chunks as $index => $chunk) {
                $embeddingDTO = $embeddings[$index] ?? null;

                if ($embeddingDTO instanceof EmbeddingDTO) {
                    $chunk->embedding_model = $embeddingDTO->model;

                    $chunk->metadata = array_merge($chunk->metadata ?? [], [
                        'embedding_vector' => $embeddingDTO->vector,
                        'embedding_dimension' => $embeddingDTO->dimension,
                    ]);

                    $chunk->save();

                    $results[] = $chunk;
                } else {
                    $this->logService->warning('Invalid embedding for chunk', [
                        'chunk_id' => $chunk->id,
                        'model' => $model,
                    ]);
                }
            }
        } catch (\Exception $e) {
            $this->logService->error('Embedding generation failed', [
                'model' => $model,
                'chunk_count' => count($chunks),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            throw $e;
        }

        return $results;
    }

    /**
     * Generate embedding for a single text.
     *
     * @param string $text The text to generate embedding for
     * @param string $model The embedding model to use
     * @return EmbeddingDTO The embedding DTO
     */
    public function generateEmbedding(string $text, string $model): EmbeddingDTO
    {
        return $this->embeddingProvider->generateEmbedding($text, $model);
    }

    /**
     * Get embedding dimension for a specific model.
     *
     * @param string $model The embedding model name
     * @return int The dimension of the embedding vector
     */
    public function getEmbeddingDimension(string $model): int
    {
        return $this->embeddingProvider->getEmbeddingDimension($model);
    }
}
