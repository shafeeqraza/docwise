<?php

namespace App\Domains\RAG\Contracts;

use App\Domains\RAG\DTOs\ChunkDTO;

/**
 * Contract for storing and retrieving vectors.
 *
 * Follows Interface Segregation Principle (ISP): Only vector storage/retrieval methods.
 */
interface VectorStore
{
    /**
     * Ensure the vector store collection exists.
     *
     * @param int $vectorDimension The dimension of vectors to store
     * @return bool True if collection exists or was created
     * @throws \RuntimeException If collection cannot be created
     */
    public function ensureCollection(int $vectorDimension = 1536): bool;

    /**
     * Upsert chunks with their embeddings to the vector store.
     *
     * @param array<ChunkDTO> $chunks Array of chunks with embeddings
     * @return array<ChunkDTO> Array of chunks with vector store IDs set
     * @throws \RuntimeException If upsert operation fails
     */
    public function upsertChunks(array $chunks): array;

    /**
     * Search for similar vectors.
     *
     * @param array<int> $queryVector The query vector
     * @param int $limit Maximum number of results to return
     * @param array $filters Optional filters (e.g., company_id, document_id)
     * @return array<ChunkDTO> Array of matching chunks
     * @throws \RuntimeException If search operation fails
     */
    public function search(array $queryVector, int $limit = 10, array $filters = []): array;

    /**
     * Delete chunks by document ID.
     *
     * @param int $documentId The document ID
     * @return bool True if successful
     */
    public function deleteByDocument(int $documentId): bool;
}
