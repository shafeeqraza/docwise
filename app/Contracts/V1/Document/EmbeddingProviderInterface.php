<?php

namespace App\Contracts\V1\Document;

interface EmbeddingProviderInterface
{
    /**
     * Generate embedding for a single text.
     *
     * @param string $text The text to generate embedding for
     * @param string $model The embedding model to use
     * @return array The embedding vector
     * @throws \App\Exceptions\EmbeddingException
     */
    public function generateEmbedding(string $text, string $model): array;

    /**
     * Generate embeddings for multiple texts in batch.
     *
     * @param array $texts Array of texts to generate embeddings for
     * @param string $model The embedding model to use
     * @return array Array of embedding vectors
     * @throws \App\Exceptions\EmbeddingException
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
