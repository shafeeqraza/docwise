<?php

namespace App\Repositories\V1;

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
}
