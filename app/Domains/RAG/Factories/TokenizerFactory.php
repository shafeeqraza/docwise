<?php

namespace App\Domains\RAG\Factories;

use App\Domains\RAG\Tokenizers\Gemini\GeminiTokenizer;
use App\Domains\RAG\Tokenizers\Tiktoken\TiktokenTokenizer;
use App\Domains\RAG\Tokenizers\TokenizerInterface;
use Illuminate\Contracts\Container\Container;

/**
 * Factory for creating tokenizers based on model name.
 */
class TokenizerFactory
{
    /**
     * Mapping of tokenizer classes.
     *
     * @var array<string, class-string<TokenizerInterface>>
     */
    private array $tokenizers = [
        'gemini' => GeminiTokenizer::class,
        'openai' => TiktokenTokenizer::class,
    ];

    public function __construct(
        private Container $container
    ) {}

    /**
     * Create a tokenizer for the given model.
     *
     * @param string $model The embedding model name
     * @return TokenizerInterface The appropriate tokenizer instance
     * @throws \RuntimeException If no tokenizer can be created for the model
     */
    public function create(string $model): TokenizerInterface
    {
        $normalized = strtolower($model);

        // Check if model is Gemini
        if (str_contains($normalized, 'gemini') || str_starts_with($normalized, 'models/gemini')) {
            return $this->container->make($this->tokenizers['gemini'], ['model' => $model]);
        }

        // Check if model is OpenAI
        if (
            str_contains($normalized, 'openai') ||
            str_contains($normalized, 'text-embedding') ||
            str_contains($normalized, 'ada')
        ) {
            return $this->container->make($this->tokenizers['openai'], ['model' => $model]);
        }

        throw new \RuntimeException("No tokenizer available for model: {$model}");
    }
}
