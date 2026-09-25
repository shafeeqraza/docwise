<?php

namespace App\Domains\RAG\VectorStores;

use App\Domains\RAG\Attributes\DriverDiscovery;
use App\Domains\RAG\Attributes\VectorStoreDriver;
use App\Domains\RAG\Contracts\VectorStore;
use Illuminate\Support\Manager;
use Illuminate\Support\Str;

/**
 * Vector Store Manager for managing multiple vector store drivers.
 *
 * Follows Laravel Manager Pattern: Allows switching between drivers via configuration.
 * Follows Open/Closed Principle (OCP): Drivers are discovered from the #[VectorStoreDriver]
 * attribute, so adding one means writing a tagged class and a config block — no change here.
 */
class VectorStoreManager extends Manager
{
    /**
     * Discovered drivers, keyed by driver name.
     *
     * @var array<string, class-string<VectorStore>>|null
     */
    private ?array $discoveredDrivers = null;

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
     * Get the drivers declared with #[VectorStoreDriver] in this directory.
     *
     * @return array<string, class-string<VectorStore>>
     */
    public function availableDrivers(): array
    {
        return $this->discoveredDrivers ??= DriverDiscovery::discover(
            __DIR__,
            __NAMESPACE__,
            VectorStoreDriver::class,
            VectorStore::class
        );
    }

    /**
     * Create a driver instance, preferring extend() creators, then attribute-discovered classes.
     *
     * @param string $driver
     * @return VectorStore
     * @throws \InvalidArgumentException If the driver is not supported
     */
    protected function createDriver($driver)
    {
        if (!isset($this->customCreators[$driver]) && isset($this->availableDrivers()[$driver])) {
            return $this->container->make(
                $this->availableDrivers()[$driver],
                $this->parametersFor($driver)
            );
        }

        return parent::createDriver($driver);
    }

    /**
     * Map a driver's config block to camelCase constructor parameters.
     *
     * Keys that match no constructor parameter are ignored by the container.
     *
     * @param string $driver
     * @return array<string, mixed>
     */
    protected function parametersFor(string $driver): array
    {
        return collect(config("vectorstore.drivers.{$driver}", []))
            ->except('driver')
            ->mapWithKeys(fn($value, $key) => [Str::camel($key) => $value])
            ->all();
    }
}
