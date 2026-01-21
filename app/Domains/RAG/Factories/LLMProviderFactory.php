<?php

namespace App\Domains\RAG\Factories;

use App\Domains\RAG\Contracts\LLMProvider;
use App\Domains\RAG\LLMs\Gemini\GeminiLLMProvider;
use App\Domains\RAG\LLMs\OpenAI\OpenAILLMProvider;
use Illuminate\Contracts\Container\Container;

/**
 * Factory for creating LLM providers based on model name.
 *
 * Follows Single Responsibility Principle (SRP): Only responsible for provider creation.
 * Follows Open/Closed Principle (OCP): Easy to add new providers without modifying factory.
 * Follows Dependency Inversion Principle (DIP): Returns interface type, uses container for resolution.
 */
class LLMProviderFactory
{
    /**
     * Registered providers.
     *
     * @var array<LLMProvider>
     */
    private array $providers = [];

    public function __construct(
        private Container $container
    ) {
        // Register default providers
        $this->registerDefaultProviders();
    }

    /**
     * Register default LLM providers.
     *
     * @return void
     */
    private function registerDefaultProviders(): void
    {
        $this->providers[] = $this->container->make(GeminiLLMProvider::class);
        $this->providers[] = $this->container->make(OpenAILLMProvider::class);
    }

    /**
     * Create an LLM provider for the given model.
     *
     * @param string $model The LLM model name
     * @return LLMProvider The appropriate provider instance
     * @throws \RuntimeException If no provider supports the model
     */
    public function create(string $model): LLMProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($model)) {
                return $provider;
            }
        }

        throw new \RuntimeException(
            "No LLM provider found that supports model: {$model}. " .
                "Available providers: " . $this->getProviderNames()
        );
    }

    /**
     * Register a custom LLM provider.
     *
     * @param LLMProvider $provider The provider instance
     * @return void
     */
    public function register(LLMProvider $provider): void
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
