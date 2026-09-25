<?php

namespace App\Domains\RAG\Factories;

use App\Domains\RAG\Attributes\DriverDiscovery;
use App\Domains\RAG\Attributes\EmbeddingDriver;
use App\Domains\RAG\Contracts\EmbeddingProvider;
use App\Domains\RAG\Exceptions\EmbeddingFailedException;
use Illuminate\Contracts\Container\Container;

/**
 * Factory for creating embedding providers based on model name.
 *
 * Follows Single Responsibility Principle (SRP): Only responsible for provider creation.
 * Follows Open/Closed Principle (OCP): Providers are discovered from the #[EmbeddingDriver]
 * attribute, so adding one needs no change to this factory.
 * Follows Dependency Inversion Principle (DIP): Returns interface type, uses container for resolution.
 */
class EmbeddingProviderFactory
{
    /**
     * Discovered provider classes, keyed by driver name.
     *
     * @var array<string, class-string<EmbeddingProvider>>
     */
    private array $drivers;

    /**
     * Resolved provider instances, keyed by driver name.
     *
     * @var array<string, EmbeddingProvider>
     */
    private array $resolved = [];

    /**
     * Providers registered at runtime; checked before discovered ones.
     *
     * @var array<EmbeddingProvider>
     */
    private array $registered = [];

    public function __construct(
        private Container $container
    ) {
        $this->drivers = DriverDiscovery::discover(
            app_path('Domains/RAG/Embeddings'),
            'App\\Domains\\RAG\\Embeddings',
            EmbeddingDriver::class,
            EmbeddingProvider::class
        );
    }

    /**
     * Create an embedding provider for the given model.
     *
     * @param string $model The embedding model name
     * @return EmbeddingProvider The appropriate provider instance
     * @throws EmbeddingFailedException If no provider supports the model
     */
    public function create(string $model): EmbeddingProvider
    {
        foreach ($this->registered as $provider) {
            if ($provider->supports($model)) {
                return $provider;
            }
        }

        foreach (array_keys($this->drivers) as $name) {
            $provider = $this->driver($name);

            if ($provider->supports($model)) {
                return $provider;
            }
        }

        throw new EmbeddingFailedException(
            "No embedding provider found that supports model: {$model}. " .
                "Available providers: " . $this->getProviderNames()
        );
    }

    /**
     * Get an embedding provider by its #[EmbeddingDriver] name.
     *
     * @param string $name The driver name (e.g. 'gemini')
     * @return EmbeddingProvider
     * @throws EmbeddingFailedException If no provider is declared with that name
     */
    public function driver(string $name): EmbeddingProvider
    {
        if (!isset($this->drivers[$name])) {
            throw new EmbeddingFailedException(
                "Unknown embedding driver: {$name}. Available providers: " . $this->getProviderNames()
            );
        }

        return $this->resolved[$name] ??= $this->container->make($this->drivers[$name]);
    }

    /**
     * Register a custom embedding provider.
     *
     * @param EmbeddingProvider $provider The provider instance
     * @return void
     */
    public function register(EmbeddingProvider $provider): void
    {
        $this->registered[] = $provider;
    }

    /**
     * Get names of all available providers.
     *
     * @return string Comma-separated list of driver names and registered provider classes
     */
    private function getProviderNames(): string
    {
        return implode(', ', [
            ...array_keys($this->drivers),
            ...array_map(fn($p) => get_class($p), $this->registered),
        ]);
    }
}
