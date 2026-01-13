<?php

namespace App\Repositories\V1;

use App\Models\DocumentChunk;

class DocumentChunkRepository implements DocumentChunkRepositoryInterface
{
    /**
     * Create a new document chunk.
     *
     * @param array $data Chunk data
     * @return DocumentChunk The created chunk
     */
    public function create(array $data): DocumentChunk
    {
        return DocumentChunk::create($data);
    }

    /**
     * Create multiple chunks in batch.
     *
     * @param array<array> $chunksData Array of chunk data arrays
     * @return array<DocumentChunk> Array of created chunks
     */
    public function createBatch(array $chunksData): array
    {
        $chunks = [];
        foreach ($chunksData as $data) {
            $chunks[] = $this->create($data);
        }
        return $chunks;
    }

    /**
     * Find chunk by ID.
     *
     * @param int $id Chunk ID
     * @return DocumentChunk|null
     */
    public function findById(int $id): ?DocumentChunk
    {
        return DocumentChunk::find($id);
    }

    /**
     * Find chunks by document ID.
     *
     * @param int $documentId Document ID
     * @return array<DocumentChunk>
     */
    public function findByDocumentId(int $documentId): array
    {
        return DocumentChunk::where('document_id', $documentId)
            ->orderBy('chunk_index')
            ->get()
            ->all();
    }

    /**
     * Find chunks by version ID.
     *
     * @param int $versionId Version ID
     * @return array<DocumentChunk>
     */
    public function findByVersionId(int $versionId): array
    {
        return DocumentChunk::where('version_id', $versionId)
            ->orderBy('chunk_index')
            ->get()
            ->all();
    }

    /**
     * Delete chunks by document ID.
     *
     * @param int $documentId Document ID
     * @return bool True if successful
     */
    public function deleteByDocumentId(int $documentId): bool
    {
        return DocumentChunk::where('document_id', $documentId)->delete() > 0;
    }
}
