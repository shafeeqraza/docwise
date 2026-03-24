<?php

namespace App\Domains\RAG\Services;

use App\Domains\RAG\Contracts\VectorStore;
use App\Domains\RAG\DTOs\ChunkDTO;
use App\Domains\RAG\DTOs\EmbeddingDTO;
use App\Domains\RAG\Exceptions\QdrantException as RAGQdrantException;
use App\Domains\RAG\Exceptions\VectorStoreException as RAGVectorStoreException;
use App\Exceptions\VectorStoreException;
use App\Models\DocumentChunk;
use App\Services\V1\Common\LogService;
use Illuminate\Support\Facades\Config;

/**
 * Service for storing and retrieving vectors.
 *
 * Follows Adapter Pattern: Adapts domain vector store to application layer needs.
 * Driver-agnostic: works with any configured vector store (Qdrant, PostgreSQL, etc.).
 */
class VectorStoreService
{
    public function __construct(
        private VectorStore $vectorStore,
        private LogService $logService
    ) {}

    /**
     * Ensure the vector store collection/schema exists.
     *
     * @return bool True if collection exists or was created
     * @throws VectorStoreException If collection cannot be created
     */
    public function ensureCollection(): bool
    {
        try {
            return $this->vectorStore->ensureCollection();
        } catch (RAGVectorStoreException|RAGQdrantException $e) {
            throw new VectorStoreException($e->getMessage(), $e->getCode(), $e);
        } catch (\Exception $e) {
            throw new VectorStoreException(
                "Failed to ensure collection: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Upsert chunks to the configured vector store.
     *
     * @param array<DocumentChunk> $chunks Array of DocumentChunk models with embeddings
     * @return array<DocumentChunk> Array of chunks (with vector store IDs set when driver is Qdrant)
     * @throws VectorStoreException If upsert operation fails
     */
    public function upsertChunks(array $chunks): array
    {
        if (empty($chunks)) {
            return [];
        }

        try {
            $chunkDTOs = $this->convertToDTOs($chunks);
            $this->vectorStore->upsertChunks($chunkDTOs);

            // Only persist vector store IDs when using Qdrant (pgsql stores vectors in same table)
            if (Config::get('vectorstore.default') === 'qdrant') {
                foreach ($chunks as $chunk) {
                    if (isset($chunk->metadata['embedding_vector']) && $chunk->metadata['embedding_vector']) {
                        $chunk->qdrant_point_id = $chunk->uuid ?? (string) $chunk->id;
                        $chunk->qdrant_collection = Config::get('vectorstore.drivers.qdrant.collection_name', 'documents');
                        $chunk->save();
                    }
                }
            }

            return $chunks;
        } catch (RAGVectorStoreException|RAGQdrantException $e) {
            throw new VectorStoreException($e->getMessage(), $e->getCode(), $e);
        } catch (\Exception $e) {
            $this->logService->error('Vector store upsert failed', ['error' => $e->getMessage()]);
            throw new VectorStoreException("Failed to upsert chunks: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Search for similar vectors.
     *
     * @param array<int> $queryVector The query vector
     * @param int $limit Maximum number of results to return
     * @param array<string, mixed> $filters Optional filters (e.g., company_id, document_id)
     * @return array<ChunkDTO> Array of matching chunks
     * @throws VectorStoreException If search operation fails
     */
    public function search(array $queryVector, int $limit = 10, array $filters = []): array
    {
        try {
            return $this->vectorStore->search($queryVector, $limit, $filters);
        } catch (RAGVectorStoreException|RAGQdrantException $e) {
            throw new VectorStoreException($e->getMessage(), $e->getCode(), $e);
        } catch (\Exception $e) {
            $this->logService->error('Vector store search failed', ['error' => $e->getMessage()]);
            throw new VectorStoreException("Failed to search vectors: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Delete vectors for a document by document ID.
     *
     * @param int $documentId The document ID
     * @return bool True if successful
     */
    public function deleteByDocument(int $documentId): bool
    {
        try {
            return $this->vectorStore->deleteByDocument($documentId);
        } catch (\Exception $e) {
            $this->logService->error('Vector store delete failed', [
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
                    $this->logService->warning('Failed to create EmbeddingDTO from chunk metadata', [
                        'chunk_id' => $chunk->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $dtos[] = new ChunkDTO(
                content: $chunk->content,
                index: $chunk->chunk_index,
                tokens: (int) ($chunk->token_count ?? 0),
                id: $chunk->id,
                documentId: $chunk->document_id,
                versionId: $chunk->version_id,
                companyId: $chunk->company_id,
                metadata: $chunk->metadata ?? [],
                embedding: $embeddingDTO
            );
        }
        return $dtos;
    }
}
