<?php

namespace Tests\Unit\Domains\RAG;

use App\Domains\RAG\Attributes\DriverDiscovery;
use App\Domains\RAG\Attributes\EmbeddingDriver;
use App\Domains\RAG\Attributes\VectorStoreDriver;
use App\Domains\RAG\Contracts\EmbeddingProvider;
use App\Domains\RAG\Contracts\VectorStore;
use App\Domains\RAG\Embeddings\Gemini\GeminiEmbeddingProvider;
use App\Domains\RAG\VectorStores\PgSQL\PgVectorStore;
use App\Domains\RAG\VectorStores\Qdrant\QdrantVectorStore;
use LogicException;
use Tests\Fixtures\FakeDriver;
use Tests\TestCase;

class DriverDiscoveryTest extends TestCase
{
    public function test_discovers_vector_store_drivers_from_attributes(): void
    {
        $drivers = DriverDiscovery::discover(
            app_path('Domains/RAG/VectorStores'),
            'App\\Domains\\RAG\\VectorStores',
            VectorStoreDriver::class,
            VectorStore::class
        );

        $this->assertSame([
            'pgsql' => PgVectorStore::class,
            'qdrant' => QdrantVectorStore::class,
        ], $drivers);
    }

    public function test_discovers_embedding_drivers_from_attributes(): void
    {
        $drivers = DriverDiscovery::discover(
            app_path('Domains/RAG/Embeddings'),
            'App\\Domains\\RAG\\Embeddings',
            EmbeddingDriver::class,
            EmbeddingProvider::class
        );

        $this->assertSame(['gemini' => GeminiEmbeddingProvider::class], $drivers);
    }

    public function test_throws_when_two_classes_claim_the_same_driver_name(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Driver [duplicate] is declared by both');

        DriverDiscovery::discover(
            base_path('tests/Fixtures/DuplicateDrivers'),
            'Tests\\Fixtures\\DuplicateDrivers',
            VectorStoreDriver::class,
            FakeDriver::class
        );
    }
}
