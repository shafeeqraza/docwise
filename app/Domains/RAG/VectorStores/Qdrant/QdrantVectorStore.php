<?php

namespace App\Domains\RAG\VectorStores\Qdrant;

use App\Domains\RAG\Contracts\VectorStore;
use App\Domains\RAG\DTOs\ChunkDTO;
use App\Domains\RAG\Exceptions\QdrantException;
use App\Services\V1\Common\LogService;
use Illuminate\Support\Facades\Http;

/**
 * Qdrant vector store implementation.
 *
 * Follows Single Responsibility Principle (SRP): Only Qdrant-specific vector storage logic.
 * Follows Liskov Substitution Principle (LSP): Fully implements VectorStore interface.
 */
class QdrantVectorStore implements VectorStore
{
    private const DEFAULT_VECTOR_DIMENSION = 1536;
    private const DOCUMENT_COLLECTION_NAME = 'documents';

    /**
     * Create a new Qdrant vector store instance.
     *
     * @param LogService $logService
     */
    public function __construct(
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
        $apiKey = config('qdrant.api_key');

        try {
            // Check if collection exists
            $url = $this->buildQdrantUrl("/collections/{$collectionName}");
            $headers = $apiKey ? ['api-key' => $apiKey] : [];

            $response = Http::withHeaders($headers)->get($url);

            if ($response->successful()) {
                return true; // Collection exists
            }

            // Create collection if it doesn't exist
            $createUrl = $this->buildQdrantUrl("/collections/{$collectionName}");
            $createResponse = Http::withHeaders($headers)->put($createUrl, [
                'vectors' => [
                    'size' => $vectorDimension,
                    'distance' => 'Cosine', // Cosine similarity
                ],
            ]);

            if (!$createResponse->successful()) {
                throw new QdrantException('Failed to create Qdrant collection: ' . $createResponse->body());
            }

            // Create index for company_id filtering
            $createIndexUrl = $this->buildQdrantUrl("/collections/{$collectionName}/index");
            $createIndexResponse = Http::withHeaders($headers)->put($createIndexUrl, [
                'field_name' => 'company_id',
                'field_schema' => 'keyword',
            ]);

            if (!$createIndexResponse->successful()) {
                throw new QdrantException('Failed to create Qdrant index: ' . $createIndexResponse->body());
            }

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
        $apiKey = config('qdrant.api_key');

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

        try {
            $url = $this->buildQdrantUrl("/collections/{$collectionName}/points");
            $headers = $apiKey ? ['api-key' => $apiKey] : [];

            $response = Http::withHeaders($headers)->put($url, [
                'points' => $points,
            ]);

            if (!$response->successful()) {
                throw new QdrantException('Failed to upsert points to Qdrant: ' . $response->body());
            }

            $this->logService->info('Upserted chunks to Qdrant', [
                'collection' => $collectionName,
                'count' => count($points),
            ]);

            return $chunks;
        } catch (\Exception $e) {
            $this->logService->error('Qdrant upsert failed', [
                'collection' => $collectionName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
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
        $apiKey = config('qdrant.api_key');

        try {
            $url = $this->buildQdrantUrl("/collections/{$collectionName}/points/search");
            $headers = $apiKey ? ['api-key' => $apiKey] : [];

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

            $response = Http::withHeaders($headers)->post($url, $payload);

            if (!$response->successful()) {
                throw new QdrantException('Failed to search Qdrant: ' . $response->body());
            }

            $results = $response->json();
            $chunks = [];

            foreach ($results['result'] ?? [] as $point) {
                $payload = $point['payload'] ?? [];
                $chunks[] = new ChunkDTO(
                    content: $payload['content'] ?? '',
                    index: $payload['chunk_index'] ?? 0,
                    metadata: $payload,
                    tokens: $payload['token_count'] ?? null,
                    embedding: null, // Not returned in search results
                    id: $payload['chunk_id'] ?? null,
                    documentId: $payload['document_id'] ?? null,
                    versionId: $payload['version_id'] ?? null,
                    companyId: $payload['company_id'] ?? null
                );
            }

            return $chunks;
        } catch (\Exception $e) {
            $this->logService->error('Qdrant search failed', [
                'collection' => $collectionName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
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
        $apiKey = config('qdrant.api_key');

        try {
            $url = $this->buildQdrantUrl("/collections/{$collectionName}/points/delete");
            $headers = $apiKey ? ['api-key' => $apiKey] : [];

            $response = Http::withHeaders($headers)->post($url, [
                'filter' => [
                    'must' => [
                        [
                            'key' => 'document_id',
                            'match' => ['value' => $documentId],
                        ],
                    ],
                ],
            ]);

            if (!$response->successful()) {
                throw new QdrantException('Failed to delete points from Qdrant: ' . $response->body());
            }

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
     * Get Qdrant collection name.
     *
     * @return string Collection name
     */
    private function getCollectionName(): string
    {
        return self::DOCUMENT_COLLECTION_NAME;
    }

    /**
     * Build Qdrant URL, handling both localhost and cloud instances.
     *
     * @param string $path The API path
     * @return string The complete URL
     */
    private function buildQdrantUrl(string $path): string
    {
        $host = config('qdrant.host', 'localhost');
        $port = config('qdrant.port', 6333);

        // Remove any existing protocol from host
        $host = preg_replace('#^https?://#', '', $host);

        // Determine protocol: use https for cloud instances, http for localhost
        $protocol = ($host !== 'localhost' && $host !== '127.0.0.1') ? 'https' : 'http';

        // For HTTPS (cloud), don't include port (uses default 443)
        // For HTTP (localhost), include port
        if ($protocol === 'https') {
            return "https://{$host}{$path}";
        } else {
            return "http://{$host}:{$port}{$path}";
        }
    }
}
