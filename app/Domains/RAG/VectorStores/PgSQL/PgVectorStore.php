<?php

namespace App\Domains\RAG\VectorStores\PgSQL;

use App\Domains\RAG\Attributes\VectorStoreDriver;
use App\Domains\RAG\Contracts\VectorStore;
use App\Domains\RAG\DTOs\ChunkDTO;
use App\Domains\RAG\Exceptions\VectorStoreException;
use App\Domains\RAG\VectorStores\Concerns\RetriesVectorStoreOperations;
use App\Models\DocumentChunk;
use App\Services\V1\Common\LogService;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PostgreSQL (pgvector) vector store implementation.
 *
 * Uses the DocumentChunk Eloquent model for queries; raw SQL only for
 * pgvector extension, vector column DDL, and vector similarity expressions.
 */
#[VectorStoreDriver('pgsql')]
class PgVectorStore implements VectorStore
{
    use RetriesVectorStoreOperations;

    private const DEFAULT_VECTOR_DIMENSION = 1536;
    private const MAX_RETRIES = 3;
    private const MSG_ALREADY_EXISTS = 'already exists';

    /**
     * Create a new PostgreSQL vector store instance.
     *
     * @param string $connection The database connection name
     * @param string $table The table name
     * @param string $vectorColumn The vector column name
     * @param int $defaultDimension Default vector dimension
     * @param LogService $logService
     */
    public function __construct(
        private readonly string $connection,
        private readonly string $table,
        private readonly string $vectorColumn,
        private readonly int $defaultDimension,
        private readonly LogService $logService
    ) {}

    /**
     * Ensure the pgvector extension and necessary indexes exist.
     *
     * @param int $vectorDimension The dimension of vectors to store
     * @return bool True if extension and indexes exist or were created
     * @throws VectorStoreException If setup fails
     */
    #[\Override]
    public function ensureCollection(int $vectorDimension = self::DEFAULT_VECTOR_DIMENSION): bool
    {
        try {
            // Ensure pgvector extension is installed
            $this->ensurePgVectorExtension();

            // Ensure vector column exists with correct type
            $this->ensureVectorColumn($vectorDimension);

            // Ensure indexes exist for efficient similarity search
            $this->ensureIndexes();

            return true;
        } catch (\Exception $e) {
            $this->logService->error('PostgreSQL vector store setup failed', [
                'error' => $e->getMessage(),
                'dimension' => $vectorDimension,
            ]);
            throw new VectorStoreException(
                "Failed to ensure PostgreSQL vector store collection: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Upsert chunks with their embeddings to PostgreSQL.
     *
     * @param array<ChunkDTO> $chunks Array of chunks with embeddings
     * @return array<ChunkDTO> Array of chunks (unchanged, as vectors are stored in DB)
     * @throws VectorStoreException If upsert operation fails
     */
    #[\Override]
    public function upsertChunks(array $chunks): array
    {
        if (empty($chunks)) {
            return [];
        }

        // Determine vector dimension from first chunk
        $vectorDimension = $chunks[0]->embedding?->dimension ?? $this->defaultDimension;

        // Ensure collection exists
        $this->ensureCollection($vectorDimension);

        return $this->retryWithBackoff(
            function () use ($chunks) {
                return $this->performUpsert($chunks);
            },
            'PostgreSQL upsert',
            self::MAX_RETRIES,
            ['table' => $this->table, 'count' => count($chunks)]
        );
    }

    /**
     * Perform the actual upsert operation using the DocumentChunk model.
     *
     * @param array<ChunkDTO> $chunks Array of chunks with embeddings
     * @return array<ChunkDTO> Array of chunks
     * @throws VectorStoreException If upsert operation fails
     */
    private function performUpsert(array $chunks): array
    {
        $toUpdate = [];

        foreach ($chunks as $chunk) {
            if (!$chunk->embedding) {
                continue;
            }
            $vectorString = '[' . implode(',', $chunk->embedding->vector) . ']';
            $toUpdate[] = ['id' => $chunk->id, 'vector' => $vectorString];
        }

        if (empty($toUpdate)) {
            return $chunks;
        }

        foreach ($toUpdate as $u) {
            DocumentChunk::on($this->connection)
                ->where('id', $u['id'])
                ->update([
                    $this->vectorColumn => new Expression("'{$u['vector']}'::vector"),
                ]);
        }

        return $chunks;
    }

    /**
     * Search for similar vectors using cosine similarity.
     *
     * @param array<int> $queryVector The query vector
     * @param int $limit Maximum number of results to return
     * @param array<string, mixed> $filters Optional filters (e.g., company_id, document_id)
     * @return array<ChunkDTO> Array of matching chunks
     * @throws VectorStoreException If search operation fails
     */
    #[\Override]
    public function search(array $queryVector, int $limit = 10, array $filters = []): array
    {
        return $this->retryWithBackoff(
            function () use ($queryVector, $limit, $filters) {
                return $this->performSearch($queryVector, $limit, $filters);
            },
            'PostgreSQL search',
            self::MAX_RETRIES,
            ['table' => $this->table, 'limit' => $limit]
        );
    }

    /**
     * Perform the actual search using the DocumentChunk model and pgvector similarity.
     *
     * @param array<int> $queryVector The query vector
     * @param int $limit Maximum number of results to return
     * @param array<string, mixed> $filters Optional filters
     * @return array<ChunkDTO> Array of matching chunks
     * @throws VectorStoreException If search operation fails
     */
    private function performSearch(array $queryVector, int $limit, array $filters): array
    {
        $vectorString = '[' . implode(',', $queryVector) . ']';
        $allowedFilterKeys = ['company_id', 'document_id', 'version_id'];

        $query = DocumentChunk::on($this->connection)
            ->selectRaw("*, 1 - ({$this->vectorColumn} <=> ?::vector) as similarity_score", [$vectorString])
            ->whereNotNull($this->vectorColumn);

        foreach ($filters as $key => $value) {
            if (in_array($key, $allowedFilterKeys, true)) {
                $query->where($key, $value);
            }
        }

        $rows = $query
            ->orderByRaw("{$this->vectorColumn} <=> ?::vector", [$vectorString])
            ->limit($limit)
            ->get();

        $chunks = [];
        foreach ($rows as $row) {
            $metadata = $row->metadata ?? [];
            $metadata['similarity_score'] = (float) $row->similarity_score;

            $chunks[] = new ChunkDTO(
                content: $row->content ?? '',
                index: (int) ($row->chunk_index ?? 0),
                tokens: (int) ($row->token_count ?? 0),
                id: $row->id,
                documentId: (int) $row->document_id,
                versionId: (int) $row->version_id,
                companyId: (int) $row->company_id,
                metadata: $metadata,
                embedding: null
            );
        }

        return $chunks;
    }

    /**
     * Delete vectors for a document by clearing the embedding column (chunk rows remain).
     *
     * @param int $documentId The document ID
     * @return bool True if successful
     */
    #[\Override]
    public function deleteByDocument(int $documentId): bool
    {
        try {
            DocumentChunk::on($this->connection)
                ->where('document_id', $documentId)
                ->update([$this->vectorColumn => null]);

            $this->logService->info('Deleted document vectors from PostgreSQL', [
                'table' => $this->table,
                'document_id' => $documentId,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logService->error('PostgreSQL delete failed', [
                'table' => $this->table,
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Ensure pgvector extension is installed.
     *
     * @return void
     * @throws VectorStoreException If extension cannot be created
     */
    private function ensurePgVectorExtension(): void
    {
        try {
            DB::connection($this->connection)->statement(
                'CREATE EXTENSION IF NOT EXISTS vector'
            );
        } catch (\Exception $e) {
            // Extension might already exist or there might be permission issues
            // Log but don't fail if it's a "already exists" error
            if (str_contains($e->getMessage(), self::MSG_ALREADY_EXISTS)) {
                return;
            }
            throw new VectorStoreException(
                "Failed to create pgvector extension: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Ensure vector column exists with correct type (raw DDL required for pgvector type).
     *
     * @param int $vectorDimension The vector dimension
     * @return void
     * @throws VectorStoreException If column cannot be created
     */
    private function ensureVectorColumn(int $vectorDimension): void
    {
        try {
            if (! Schema::connection($this->connection)->hasColumn($this->table, $this->vectorColumn)) {
                DB::connection($this->connection)->statement(
                    "ALTER TABLE {$this->table} ADD COLUMN {$this->vectorColumn} vector({$vectorDimension})"
                );
            }
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), self::MSG_ALREADY_EXISTS) || str_contains($e->getMessage(), 'duplicate')) {
                return;
            }
            throw new VectorStoreException(
                "Failed to ensure vector column: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Ensure indexes exist for efficient similarity search.
     *
     * @return void
     * @throws VectorStoreException If indexes cannot be created
     */
    private function ensureIndexes(): void
    {
        try {
            $indexName = "{$this->table}_{$this->vectorColumn}_idx";

            // Check if index exists
            $indexExists = DB::connection($this->connection)
                ->selectOne(
                    'SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                    [$this->table, $indexName]
                );

            if (!$indexExists) {
                try {
                    DB::connection($this->connection)->statement(
                        "CREATE INDEX {$indexName} ON {$this->table} USING hnsw ({$this->vectorColumn} vector_cosine_ops)"
                    );
                } catch (\Exception $e) {
                    if (str_contains($e->getMessage(), 'hnsw') || str_contains($e->getMessage(), 'HNSW')) {
                        DB::connection($this->connection)->statement(
                            "CREATE INDEX {$indexName} ON {$this->table} USING ivfflat ({$this->vectorColumn} vector_cosine_ops)"
                        );
                    } else {
                        throw $e;
                    }
                }
            }
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), self::MSG_ALREADY_EXISTS) || str_contains($e->getMessage(), 'duplicate')) {
                return;
            }
            $this->logService->warning('Failed to create vector index', [
                'table' => $this->table,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - index creation is optional for functionality
        }
    }
}
