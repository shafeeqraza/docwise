<?php

namespace App\Domains\RAG\Factories;

use App\Domains\RAG\Tokenizers\Gemini\GeminiTokenizer;
use App\Domains\RAG\Tokenizers\Tiktoken\TiktokenTokenizer;
use App\Domains\RAG\Tokenizers\TokenizerInterface;
use Illuminate\Contracts\Container\Container;

/**
 * Factory for creating tokenizers by name.
 *
 * Supported tokenizers:
 * - 'gemini': GeminiTokenizer (API-based, 100% accurate)
 * - 'openai': TiktokenTokenizer (local, for OpenAI models)
 * - 'tiktoken': TiktokenTokenizer (local, fast approximation)
 *
 * Follows Factory Pattern: Centralized tokenizer creation.
 * Follows Open/Closed Principle: Easy to add new tokenizers.
 */
class TokenizerFactory
{
    /**
     * Mapping of tokenizer names to classes.
     *
     * @var array<string, class-string<TokenizerInterface>>
     */
    private array $tokenizers = [
        'gemini' => GeminiTokenizer::class,
        'openai' => TiktokenTokenizer::class,
        'tiktoken' => TiktokenTokenizer::class,
    ];

    public function __construct(
        private Container $container
    ) {}

    /**
     * Create a tokenizer by name.
     *
     * @param string $name The tokenizer name ('gemini', 'openai', 'tiktoken')
     * @param string|null $model Optional model name for the tokenizer
     * @return TokenizerInterface The tokenizer instance
     * @throws \RuntimeException If tokenizer name is not supported
     */
    public function create(string $name, ?string $model = null): TokenizerInterface
    {
        $normalized = strtolower($name);

        if (!isset($this->tokenizers[$normalized])) {
            throw new \RuntimeException("Unknown tokenizer: {$name}. Supported: " . implode(', ', array_keys($this->tokenizers)));
        }

        $class = $this->tokenizers[$normalized];

        // Resolve model parameter
        $params = [];
        if ($model !== null) {
            $params['model'] = $model;
        } elseif ($normalized === 'tiktoken') {
            // Default to gpt-4 for tiktoken (cl100k_base encoding)
            $params['model'] = 'gpt-4';
        }

        return $this->container->make($class, $params);
    }

    /**
     * Create a Gemini tokenizer (API-based, 100% accurate).
     *
     * @param string $model The Gemini model name
     * @return TokenizerInterface Gemini tokenizer instance
     */
    public function gemini(string $model = 'models/gemini-embedding-001'): TokenizerInterface
    {
        return $this->create('gemini', $model);
    }

    /**
     * Create a Tiktoken tokenizer (local, fast).
     *
     * Uses cl100k_base encoding (GPT-4) which provides ~85-90% accuracy
     * for non-OpenAI models. Ideal for chunking operations.
     *
     * @param string $model The model name (default: gpt-4)
     * @return TokenizerInterface Tiktoken tokenizer instance
     */
    public function tiktoken(string $model = 'gpt-4'): TokenizerInterface
    {
        return $this->create('tiktoken', $model);
    }

    /**
     * Create an OpenAI tokenizer (local, exact for OpenAI models).
     *
     * @param string $model The OpenAI model name
     * @return TokenizerInterface OpenAI tokenizer instance
     */
    public function openai(string $model = 'text-embedding-3-small'): TokenizerInterface
    {
        return $this->create('openai', $model);
    }

    /**
     * Create a tokenizer based on model name auto-detection.
     *
     * Useful when you have a model name and want the appropriate tokenizer.
     *
     * @param string $model The model name (e.g., 'gemini-1.5-flash', 'gpt-4', 'text-embedding-3-small')
     * @return TokenizerInterface The appropriate tokenizer instance
     * @throws \RuntimeException If model cannot be matched to a tokenizer
     */
    public function forModel(string $model): TokenizerInterface
    {
        // Gemini models -> GeminiTokenizer (API-based)
        if ($this->isGeminiModel($model)) {
            return $this->gemini($model);
        }

        // OpenAI models -> TiktokenTokenizer (local)
        if (
            $this->isOpenAIModel($model)
        ) {
            return $this->openai($model);
        }

        throw new \RuntimeException(
            "Cannot auto-detect tokenizer for model: {$model}. Use create() with explicit tokenizer name."
        );
    }

    public function isGeminiModel($model)
    {
        $normalized = strtolower($model);
        return str_contains($normalized, 'gemini') || str_starts_with($normalized, 'models/gemini');
    }

    public function isOpenAIModel($model)
    {
        $normalized = strtolower($model);
        return str_contains($normalized, 'gpt') ||
            str_contains($normalized, 'openai') ||
            str_starts_with($normalized, 'o1') ||
            str_starts_with($normalized, 'o3') ||
            str_contains($normalized, 'text-embedding') ||
            str_contains($normalized, 'ada') ||
            str_contains($normalized, 'davinci') ||
            str_contains($normalized, 'curie') ||
            str_contains($normalized, 'babbage');
    }
}
