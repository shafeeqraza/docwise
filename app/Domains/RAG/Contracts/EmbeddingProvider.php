<?php

namespace App\Domains\RAG\Contracts;

use App\Domains\RAG\DTOs\EmbeddingDTO;
use App\Domains\RAG\Exceptions\EmbeddingFailedException;

/**
 * Contract for generating embeddings from text.
 *
 * Follows Interface Segregation Principle (ISP): Only embedding-related methods.
 */
interface EmbeddingProvider
{
    /**
     * Generate embedding for a single text.
     *
     * @param string $text The text to generate embedding for
     * @param string $model The embedding model to use
     * @return EmbeddingDTO The embedding DTO
     * @throws EmbeddingFailedException If embedding generation fails
     */
    public function generateEmbedding(string $text, string $model): EmbeddingDTO;

    /**
     * Generate embeddings for multiple texts in batch.
     *
     * @param array<string> $texts Array of texts to generate embeddings for
     * @param string $model The embedding model to use
     * @return array<EmbeddingDTO> Array of embedding DTOs
     * @throws EmbeddingFailedException If embedding generation fails
     */
    public function generateEmbeddingsBatch(array $texts, string $model): array;

    /**
     * Get embedding dimension for a specific model.
     *
     * @param string $model The embedding model name
     * @return int The dimension of the embedding vector
     */
    public function getEmbeddingDimension(string $model): int;

    /**
     * Check if this provider supports the given model.
     *
     * @param string $model The embedding model name
     * @return bool True if the provider supports the model
     */
    public function supports(string $model): bool;
}
