<?php

namespace App\Repositories\V1;

use App\Models\DocumentChunk;
use App\Repositories\V1\Contracts\DocumentChunkRepositoryInterface;
use Illuminate\Support\Facades\DB;

class DocumentChunkRepository implements DocumentChunkRepositoryInterface
{
    /**
     * Create a new document chunk.
     *
     * @param array $data Chunk data
     * @return DocumentChunk The created chunk
     */
    #[\Override]
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
    #[\Override]
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
    #[\Override]
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
    #[\Override]
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
    #[\Override]
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
    #[\Override]
    public function deleteByDocumentId(int $documentId): bool
    {
        return DocumentChunk::where('document_id', $documentId)->delete() > 0;
    }

    /**
     * Batch update chunk metadata.
     *
     * @param array<int, array> $updates Map of chunk ID to metadata updates
     * @return int Number of chunks updated
     */
    #[\Override]
    public function batchUpdateMetadata(array $updates): int
    {
        if (empty($updates)) {
            return 0;
        }

        $ids = [];
        $whenClauses = [];

        foreach ($updates as $id => $metadata) {
            $ids[] = $id;
            $metadataJson = json_encode($metadata);
            $whenClauses[] = "WHEN {$id} THEN " . DB::getPdo()->quote($metadataJson) . "::json";
        }

        if (!empty($whenClauses)) {
            $whenClause = implode(' ', $whenClauses);
            $idList = implode(',', $ids);

            return DB::update(
                "UPDATE document_chunks SET metadata = CASE id {$whenClause} END WHERE id IN ({$idList})"
            );
        }

        return 0;
    }

    /**
     * Batch update Qdrant point IDs.
     *
     * @param array<int, string> $updates Map of chunk ID to Qdrant point ID
     * @param string $collectionName The Qdrant collection name
     * @return int Number of chunks updated
     */
    #[\Override]
    public function batchUpdateQdrantIds(array $updates, string $collectionName = 'documents'): int
    {
        if (empty($updates)) {
            return 0;
        }

        $whenClauses = [];
        $ids = [];

        foreach ($updates as $id => $qdrantPointId) {
            $ids[] = $id;
            $whenClauses[] = "WHEN {$id} THEN " . DB::getPdo()->quote($qdrantPointId);
        }

        if (!empty($whenClauses)) {
            $whenClause = implode(' ', $whenClauses);
            $idList = implode(',', $ids);
            $quotedCollection = DB::getPdo()->quote($collectionName);

            return DB::update(
                "UPDATE document_chunks SET
                    qdrant_point_id = CASE id {$whenClause} END,
                    qdrant_collection = {$quotedCollection}
                WHERE id IN ({$idList})"
            );
        }

        return 0;
    }

    /**
     * Batch update multiple fields for chunks.
     *
     * @param array<int, array> $updates Map of chunk ID to field updates
     * @return int Number of chunks updated
     */
    #[\Override]
    public function batchUpdate(array $updates): int
    {
        if (empty($updates)) {
            return 0;
        }

        $updatedCount = 0;

        // Update chunks individually (can be optimized further with raw SQL if needed)
        foreach ($updates as $chunkId => $fields) {
            $chunk = DocumentChunk::find($chunkId);
            if ($chunk) {
                foreach ($fields as $field => $value) {
                    $chunk->$field = $value;
                }
                $chunk->save();
                $updatedCount++;
            }
        }

        return $updatedCount;
    }
}
