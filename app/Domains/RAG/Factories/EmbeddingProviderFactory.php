<?php

namespace App\Domains\RAG\Factories;

use App\Domains\RAG\Contracts\EmbeddingProvider;
use App\Domains\RAG\Embeddings\Gemini\GeminiEmbeddingProvider;
use App\Domains\RAG\Exceptions\EmbeddingFailedException;
use Illuminate\Contracts\Container\Container;

/**
 * Factory for creating embedding providers based on model name.
 *
 * Follows Single Responsibility Principle (SRP): Only responsible for provider creation.
 * Follows Open/Closed Principle (OCP): Easy to add new providers without modifying factory.
 * Follows Dependency Inversion Principle (DIP): Returns interface type, uses container for resolution.
 */
class EmbeddingProviderFactory
{
    /**
     * Registered providers.
     *
     * @var array<EmbeddingProvider>
     */
    private array $providers = [];

    public function __construct(
        private Container $container
    ) {
        // Register default providers
        $this->registerDefaultProviders();
    }

    /**
     * Register default embedding providers.
     *
     * @return void
     */
    private function registerDefaultProviders(): void
    {
        $this->providers[] = $this->container->make(GeminiEmbeddingProvider::class);
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
        foreach ($this->providers as $provider) {
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
     * Register a custom embedding provider.
     *
     * @param EmbeddingProvider $provider The provider instance
     * @return void
     */
    public function register(EmbeddingProvider $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * Get names of all registered providers.
     *
     * @return string Comma-separated list of provider class names
     */
    private function getProviderNames(): string
    {
        return implode(', ', array_map(fn($p) => get_class($p), $this->providers));
    }
}
