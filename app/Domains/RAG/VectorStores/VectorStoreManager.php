<?php

namespace App\Domains\RAG\VectorStores;

use App\Domains\RAG\Contracts\VectorStore;
use App\Domains\RAG\VectorStores\PgSQL\PgVectorStore;
use App\Domains\RAG\VectorStores\Qdrant\Qdrant;
use App\Domains\RAG\VectorStores\Qdrant\QdrantVectorStore;
use App\Services\V1\Common\LogService;
use Illuminate\Support\Manager;

/**
 * Vector Store Manager for managing multiple vector store drivers.
 *
 * Follows Laravel Manager Pattern: Allows switching between drivers via configuration.
 * Follows Open/Closed Principle (OCP): Easy to add new drivers without modifying existing code.
 */
class VectorStoreManager extends Manager
{
    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver(): string
    {
        return config('vectorstore.default', 'qdrant');
    }

    /**
     * Create an instance of the Qdrant driver.
     *
     * @return VectorStore
     */
    protected function createQdrantDriver(): VectorStore
    {
        $qdrant = $this->container->make(Qdrant::class);
        $logService = $this->container->make(LogService::class);

        return $this->container->make(QdrantVectorStore::class, [
            'qdrant' => $qdrant,
            'logService' => $logService,
        ]);
    }

    /**
     * Create an instance of the PostgreSQL (pgvector) driver.
     *
     * @return VectorStore
     */
    protected function createPgsqlDriver(): VectorStore
    {
        $config = config('vectorstore.drivers.pgsql', []);
        $logService = $this->container->make(LogService::class);

        return $this->container->make(PgVectorStore::class, [
            'connection' => $config['connection'] ?? 'pgsql',
            'table' => $config['table'] ?? 'document_chunks',
            'vectorColumn' => $config['vector_column'] ?? 'embedding',
            'defaultDimension' => $config['default_dimension'] ?? 1536,
            'logService' => $logService,
        ]);
    }
}
