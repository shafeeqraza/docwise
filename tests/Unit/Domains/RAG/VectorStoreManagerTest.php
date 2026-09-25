<?php

namespace Tests\Unit\Domains\RAG;

use App\Domains\RAG\Contracts\VectorStore;
use App\Domains\RAG\VectorStores\PgSQL\PgVectorStore;
use App\Domains\RAG\VectorStores\Qdrant\QdrantVectorStore;
use App\Domains\RAG\VectorStores\VectorStoreManager;
use InvalidArgumentException;
use ReflectionProperty;
use Tests\TestCase;

class VectorStoreManagerTest extends TestCase
{
    private function manager(): VectorStoreManager
    {
        return new VectorStoreManager($this->app);
    }

    public function test_resolves_qdrant_driver(): void
    {
        $this->assertInstanceOf(QdrantVectorStore::class, $this->manager()->driver('qdrant'));
    }

    public function test_resolves_pgsql_driver_with_config_values(): void
    {
        config()->set('vectorstore.drivers.pgsql', [
            'driver' => 'pgsql',
            'connection' => 'pgsql_vectors',
            'table' => 'custom_chunks',
            'vector_column' => 'vec',
            'default_dimension' => 768,
        ]);

        $store = $this->manager()->driver('pgsql');

        $this->assertInstanceOf(PgVectorStore::class, $store);
        $this->assertSame('pgsql_vectors', $this->property($store, 'connection'));
        $this->assertSame('custom_chunks', $this->property($store, 'table'));
        $this->assertSame('vec', $this->property($store, 'vectorColumn'));
        $this->assertSame(768, $this->property($store, 'defaultDimension'));
    }

    public function test_default_driver_follows_config(): void
    {
        config()->set('vectorstore.default', 'pgsql');

        $this->assertInstanceOf(PgVectorStore::class, $this->manager()->driver());
    }

    public function test_unknown_driver_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver [nope] not supported.');

        $this->manager()->driver('nope');
    }

    public function test_extend_overrides_discovered_driver(): void
    {
        $fake = $this->createMock(VectorStore::class);

        $manager = $this->manager()->extend('qdrant', fn() => $fake);

        $this->assertSame($fake, $manager->driver('qdrant'));
    }

    private function property(object $object, string $name): mixed
    {
        return (new ReflectionProperty($object, $name))->getValue($object);
    }
}
