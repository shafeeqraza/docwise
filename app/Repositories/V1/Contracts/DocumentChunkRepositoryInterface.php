<?php

namespace App\Repositories\V1\Contracts;

use App\Models\DocumentChunk;

interface DocumentChunkRepositoryInterface
{
    /**
     * Create a new document chunk.
     *
     * @param array $data Chunk data
     * @return DocumentChunk The created chunk
     */
    public function create(array $data): DocumentChunk;

    /**
     * Create multiple chunks in batch.
     *
     * @param array<array> $chunksData Array of chunk data arrays
     * @return array<DocumentChunk> Array of created chunks
     */
    public function createBatch(array $chunksData): array;

    /**
     * Find chunk by ID.
     *
     * @param int $id Chunk ID
     * @return DocumentChunk|null
     */
    public function findById(int $id): ?DocumentChunk;

    /**
     * Find chunks by document ID.
     *
     * @param int $documentId Document ID
     * @return array<DocumentChunk>
     */
    public function findByDocumentId(int $documentId): array;

    /**
     * Find chunks by version ID.
     *
     * @param int $versionId Version ID
     * @return array<DocumentChunk>
     */
    public function findByVersionId(int $versionId): array;

    /**
     * Delete chunks by document ID.
     *
     * @param int $documentId Document ID
     * @return bool True if successful
     */
    public function deleteByDocumentId(int $documentId): bool;

    /**
     * Delete chunks by version ID.
     *
     * @param int $versionId Version ID
     * @return bool True if any chunks were deleted
     */
    public function deleteByVersionId(int $versionId): bool;

    /**
     * Batch update chunk metadata.
     *
     * @param array<int, array> $updates Map of chunk ID to metadata updates
     * @return int Number of chunks updated
     */
    public function batchUpdateMetadata(array $updates): int;

    /**
     * Batch update Qdrant point IDs.
     *
     * @param array<int, string> $updates Map of chunk ID to Qdrant point ID
     * @param string $collectionName The Qdrant collection name
     * @return int Number of chunks updated
     */
    public function batchUpdateQdrantIds(array $updates, string $collectionName = 'documents'): int;

    /**
     * Batch update multiple fields for chunks.
     *
     * @param array<int, array> $updates Map of chunk ID to field updates
     * @return int Number of chunks updated
     */
    public function batchUpdate(array $updates): int;
}
