<?php

namespace App\Domains\RAG\VectorStores\Qdrant;

use App\Domains\RAG\Attributes\VectorStoreDriver;
use App\Domains\RAG\Contracts\VectorStore;
use App\Domains\RAG\DTOs\ChunkDTO;
use App\Domains\RAG\Exceptions\QdrantException;
use App\Domains\RAG\VectorStores\Concerns\RetriesVectorStoreOperations;
use App\Services\V1\Common\LogService;

/**
 * Qdrant vector store implementation.
 *
 * Follows Single Responsibility Principle (SRP): Only Qdrant-specific vector storage logic.
 * Follows Liskov Substitution Principle (LSP): Fully implements VectorStore interface.
 */
#[VectorStoreDriver('qdrant')]
class QdrantVectorStore implements VectorStore
{
    use RetriesVectorStoreOperations;

    private const DEFAULT_VECTOR_DIMENSION = 1536;
    private const DOCUMENT_COLLECTION_NAME = 'documents';
    private const MAX_RETRIES = 3;

    /**
     * Create a new Qdrant vector store instance.
     *
     * @param Qdrant $qdrant The Qdrant HTTP client
     * @param LogService $logService
     */
    public function __construct(
        private readonly Qdrant $qdrant,
        private readonly LogService $logService
    ) {}

    /**
     * Ensure Qdrant collection exists.
     *
     * @param int $vectorDimension The dimension of vectors to store
     * @return bool True if collection exists or was created
     * @throws QdrantException If collection cannot be created
     */
    public function ensureCollection(int $vectorDimension = self::DEFAULT_VECTOR_DIMENSION): bool
    {
        $collectionName = $this->getCollectionName();

        try {
            // Check if collection exists
            $response = $this->qdrant->getWithoutException("/collections/{$collectionName}");

            if ($response->successful()) {
                return true; // Collection exists
            }

            // Create collection if it doesn't exist
            $this->qdrant->put("/collections/{$collectionName}", [
                'vectors' => [
                    'size' => $vectorDimension,
                    'distance' => 'Cosine', // Cosine similarity
                ],
            ]);

            // Create payload index for company_id filtering
            $this->createPayloadIndex($collectionName, 'company_id', 'integer');

            // Create payload index for document_id filtering (for deletion)
            $this->createPayloadIndex($collectionName, 'document_id', 'integer');

            return true;
        } catch (\Exception $e) {
            $this->logService->error('Qdrant collection operation failed', [
                'collection' => $collectionName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Upsert chunks with their embeddings to Qdrant.
     *
     * @param array<ChunkDTO> $chunks Array of chunks with embeddings
     * @return array<ChunkDTO> Array of chunks (with vector store IDs in metadata if needed)
     * @throws QdrantException If upsert operation fails
     */
    public function upsertChunks(array $chunks): array
    {
        if (empty($chunks)) {
            return [];
        }

        // Determine vector dimension from first chunk
        $vectorDimension = $chunks[0]->embedding?->dimension ?? self::DEFAULT_VECTOR_DIMENSION;

        // Ensure collection exists
        $this->ensureCollection($vectorDimension);

        $collectionName = $this->getCollectionName();

        return $this->retryWithBackoff(
            function () use ($chunks, $collectionName) {
                return $this->performUpsert($chunks, $collectionName);
            },
            'Qdrant upsert',
            self::MAX_RETRIES,
            ['collection' => $collectionName, 'count' => count($chunks)]
        );
    }

    /**
     * Perform the actual upsert operation.
     *
     * @param array<ChunkDTO> $chunks Array of chunks with embeddings
     * @param string $collectionName The collection name
     * @return array<ChunkDTO> Array of chunks
     * @throws QdrantException If upsert operation fails
     */
    private function performUpsert(array $chunks, string $collectionName): array
    {
        // Prepare points for batch upsert
        $points = [];
        foreach ($chunks as $chunk) {
            if (!$chunk->embedding) {
                continue; // Skip chunks without embeddings
            }

            // Use chunk UUID as vector ID (stored in metadata during persistence)
            $pointId = $chunk->metadata['uuid'] ?? $chunk->id;

            $points[] = [
                'id' => $pointId,
                'vector' => $chunk->embedding->vector,
                'payload' => [
                    'chunk_id' => $chunk->id,
                    'document_id' => $chunk->documentId,
                    'version_id' => $chunk->versionId,
                    'company_id' => $chunk->companyId,
                    'content' => $chunk->content,
                    'chunk_index' => $chunk->index,
                    'token_count' => $chunk->tokens,
                ],
            ];
        }

        if (empty($points)) {
            return $chunks;
        }

        $this->qdrant->put("/collections/{$collectionName}/points", [
            'points' => $points,
        ]);

        $this->logService->info('Upserted chunks to Qdrant', [
            'collection' => $collectionName,
            'count' => count($points),
        ]);

        return $chunks;
    }

    /**
     * Search for similar vectors.
     *
     * @param array<int> $queryVector The query vector
     * @param int $limit Maximum number of results to return
     * @param array<string, mixed> $filters Optional filters (e.g., company_id, document_id)
     * @return array<ChunkDTO> Array of matching chunks
     * @throws QdrantException If search operation fails
     */
    public function search(array $queryVector, int $limit = 10, array $filters = []): array
    {
        $collectionName = $this->getCollectionName();

        return $this->retryWithBackoff(
            function () use ($queryVector, $limit, $filters, $collectionName) {
                return $this->performSearch($queryVector, $limit, $filters, $collectionName);
            },
            'Qdrant search',
            self::MAX_RETRIES,
            ['collection' => $collectionName, 'limit' => $limit]
        );
    }

    /**
     * Perform the actual search operation.
     *
     * @param array<int> $queryVector The query vector
     * @param int $limit Maximum number of results to return
     * @param array<string, mixed> $filters Optional filters
     * @param string $collectionName The collection name
     * @return array<ChunkDTO> Array of matching chunks
     * @throws QdrantException If search operation fails
     */
    private function performSearch(array $queryVector, int $limit, array $filters, string $collectionName): array
    {
        $payload = [
            'vector' => $queryVector,
            'limit' => $limit,
            'with_payload' => true,
        ];

        // Add filters if provided
        if (!empty($filters)) {
            $must = [];
            foreach ($filters as $key => $value) {
                $must[] = [
                    'key' => $key,
                    'match' => ['value' => $value],
                ];
            }
            $payload['filter'] = ['must' => $must];
        }

        $response = $this->qdrant->post("/collections/{$collectionName}/points/search", $payload);
        $results = $response->json();
        $chunks = [];

        foreach ($results['result'] ?? [] as $point) {
            $payload = $point['payload'] ?? [];
            $score = $point['score'] ?? 0.0;

            // Add similarity score to metadata
            $metadata = $payload;
            $metadata['similarity_score'] = $score;

            $chunks[] = new ChunkDTO(
                content: $payload['content'] ?? '',
                index: $payload['chunk_index'] ?? 0,
                metadata: $metadata,
                tokens: $payload['token_count'] ?? null,
                embedding: null, // Not returned in search results
                id: $payload['chunk_id'] ?? null,
                documentId: $payload['document_id'] ?? null,
                versionId: $payload['version_id'] ?? null,
                companyId: $payload['company_id'] ?? null
            );
        }

        return $chunks;
    }

    /**
     * Delete chunks by document ID.
     *
     * @param int $documentId The document ID
     * @return bool True if successful
     */
    public function deleteByDocument(int $documentId): bool
    {
        $collectionName = $this->getCollectionName();

        try {
            $this->qdrant->post("/collections/{$collectionName}/points/delete", [
                'filter' => [
                    'must' => [
                        [
                            'key' => 'document_id',
                            'match' => ['value' => $documentId],
                        ],
                    ],
                ],
            ]);

            $this->logService->info('Deleted document chunks from Qdrant', [
                'collection' => $collectionName,
                'document_id' => $documentId,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logService->error('Qdrant delete failed', [
                'collection' => $collectionName,
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Create a payload index for filtering.
     *
     * @param string $collectionName The collection name
     * @param string $fieldName The field name to index
     * @param string $fieldType The field type (keyword, integer, float, geo, text)
     * @return bool True if successful
     */
    private function createPayloadIndex(string $collectionName, string $fieldName, string $fieldType = 'keyword'): bool
    {
        try {
            $response = $this->qdrant->putWithoutException("/collections/{$collectionName}/index", [
                'field_name' => $fieldName,
                'field_schema' => $fieldType,
            ]);

            if (!$response->successful()) {
                $this->logService->warning('Failed to create Qdrant payload index', [
                    'collection' => $collectionName,
                    'field' => $fieldName,
                    'error' => $response->body(),
                ]);
                return false;
            }

            $this->logService->info('Created Qdrant payload index', [
                'collection' => $collectionName,
                'field' => $fieldName,
                'type' => $fieldType,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logService->warning('Failed to create Qdrant payload index', [
                'collection' => $collectionName,
                'field' => $fieldName,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get Qdrant collection name.
     *
     * @return string Collection name
     */
    private function getCollectionName(): string
    {
        return self::DOCUMENT_COLLECTION_NAME;
    }
}
