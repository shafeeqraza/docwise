<?php

namespace App\Domains\RAG\Services;

use App\Domains\RAG\Contracts\VectorStore;
use App\Domains\RAG\DTOs\ChunkDTO;
use App\Domains\RAG\DTOs\EmbeddingDTO;
use App\Domains\RAG\Exceptions\QdrantException as RAGQdrantException;
use App\Exceptions\QdrantException;
use App\Models\DocumentChunk;
use Illuminate\Support\Facades\Log;

/**
 * Service for storing and retrieving vectors.
 *
 * Follows Adapter Pattern: Adapts domain vector store to application layer needs.
 * Follows Single Responsibility Principle (SRP): Only vector storage logic.
 */
class VectorStoreService
{
    public function __construct(
        private VectorStore $vectorStore
    ) {}

    /**
     * Ensure Qdrant collection exists for a company.
     *
     * @return bool True if collection exists or was created
     * @throws QdrantException If collection cannot be created
     */
    public function ensureCollection(): bool
    {
        try {
            return $this->vectorStore->ensureCollection();
        } catch (RAGQdrantException $e) {
            throw new QdrantException($e->getMessage(), $e->getCode(), $e);
        } catch (\Exception $e) {
            throw new QdrantException(
                "Failed to ensure collection: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Upsert chunks to Qdrant vector store.
     *
     * @param array<DocumentChunk> $chunks Array of DocumentChunk models with embeddings
     * @return array<DocumentChunk> Array of chunks with qdrant_point_id set
     * @throws QdrantException If upsert operation fails
     */
    public function upsertChunks(array $chunks): array
    {
        if (empty($chunks)) {
            return [];
        }

        try {
            // Convert Eloquent models to DTOs
            $chunkDTOs = $this->convertToDTOs($chunks);

            // Upsert using domain vector store
            $updatedDTOs = $this->vectorStore->upsertChunks($chunkDTOs);

            // Update Eloquent models with vector store IDs if needed
            // Note: The domain layer doesn't modify Eloquent models, so we handle persistence here
            foreach ($chunks as $index => $chunk) {
                if (isset($chunk->metadata['embedding_vector']) && $chunk->metadata['embedding_vector']) {
                    // Extract point ID from DTO metadata if available, or use chunk UUID
                    $pointId = $chunk->uuid ?? $chunk->id;
                    $chunk->qdrant_point_id = $pointId;
                    $chunk->qdrant_collection = 'documents'; // Default collection name
                    $chunk->save();
                }
            }

            return $chunks;
        } catch (RAGQdrantException $e) {
            throw new QdrantException($e->getMessage(), $e->getCode(), $e);
        } catch (\Exception $e) {
            Log::error('Vector store upsert failed', [
                'error' => $e->getMessage(),
            ]);
            throw new QdrantException(
                "Failed to upsert chunks: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Delete chunks from Qdrant by document ID.
     *
     * @param int $documentId The document ID
     * @return bool True if successful
     */
    public function deleteByDocument(int $documentId): bool
    {
        try {
            return $this->vectorStore->deleteByDocument($documentId);
        } catch (\Exception $e) {
            Log::error('Vector store delete failed', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Convert Eloquent DocumentChunk models to ChunkDTOs.
     *
     * @param array<DocumentChunk> $chunks Array of Eloquent models
     * @return array<ChunkDTO> Array of DTOs
     */
    private function convertToDTOs(array $chunks): array
    {
        $dtos = [];
        foreach ($chunks as $chunk) {
            $embeddingDTO = null;
            if (isset($chunk->metadata['embedding_vector']) && is_array($chunk->metadata['embedding_vector'])) {
                $vector = $chunk->metadata['embedding_vector'];
                $model = $chunk->embedding_model ?? 'models/gemini-embedding-001';
                $dimension = $chunk->metadata['embedding_dimension'] ?? count($vector);

                try {
                    $embeddingDTO = new EmbeddingDTO($vector, $model, $dimension);
                } catch (\InvalidArgumentException $e) {
                    Log::warning('Failed to create EmbeddingDTO from chunk metadata', [
                        'chunk_id' => $chunk->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $dtos[] = new ChunkDTO(
                content: $chunk->content,
                index: $chunk->chunk_index,
                metadata: $chunk->metadata ?? [],
                tokens: $chunk->token_count,
                embedding: $embeddingDTO,
                id: $chunk->id,
                documentId: $chunk->document_id,
                versionId: $chunk->version_id,
                companyId: $chunk->company_id
            );
        }
        return $dtos;
    }
}
