<?php

namespace App\Domains\RAG\Tokenizers\Gemini;

use App\Domains\RAG\Clients\GeminiApiClient;
use App\Domains\RAG\Tokenizers\Concerns\BatchesTokenCounting;
use App\Domains\RAG\Tokenizers\TokenizerInterface;

/**
 * Gemini tokenizer for counting tokens using Gemini API.
 *
 * Single Responsibility: Implements TokenizerInterface for Gemini models.
 * Delegates HTTP communication to GeminiApiClient.
 * Delegates batch processing logic to BatchesTokenCounting trait.
 *
 * Follows:
 * - SRP: Only tokenization interface logic
 * - LSP: Fully implements TokenizerInterface
 * - DIP: Depends on abstraction (GeminiApiClient)
 */
class GeminiTokenizer implements TokenizerInterface
{
    use BatchesTokenCounting;

    protected const DEFAULT_MODEL = 'models/gemini-embedding-001';
    protected const MAX_CONCURRENT_REQUESTS = 10;

    protected string $model;
    protected GeminiApiClient $client;

    /**
     * Create a new GeminiTokenizer instance.
     *
     * @param string $model The Gemini model name
     * @param GeminiApiClient|null $client Optional API client (for testing/DI)
     */
    public function __construct(
        string $model = self::DEFAULT_MODEL,
        ?GeminiApiClient $client = null
    ) {
        $this->model = $this->normalizeModelName($model);
        $this->client = $client ?? new GeminiApiClient();
    }

    /**
     * Count tokens in text using Gemini API.
     *
     * @param string $text The text to count tokens for
     * @return int Number of tokens
     */
    public function countTokens(string $text): int
    {
        if (empty($text)) {
            return 0;
        }

        return $this->client->countTokens($this->model, $text);
    }

    /**
     * Get text length in tokens (alias for countTokens).
     *
     * @param string $text The text to measure
     * @return int Token length
     */
    public function getTokenLength(string $text): int
    {
        return $this->countTokens($text);
    }

    /**
     * Process a batch of texts using the API client.
     * Required by BatchesTokenCounting trait.
     *
     * @param array<int, string> $batch
     * @return array<int, int>
     */
    protected function processBatch(array $batch): array
    {
        return $this->client->countTokensBatch($this->model, $batch);
    }

    /**
     * Get maximum concurrent requests for batch processing.
     * Required by BatchesTokenCounting trait.
     */
    protected function getMaxConcurrentRequests(): int
    {
        return self::MAX_CONCURRENT_REQUESTS;
    }

    /**
     * Normalize model name to ensure it has the "models/" prefix.
     */
    protected function normalizeModelName(string $model): string
    {
        if (!str_starts_with($model, 'models/')) {
            return "models/{$model}";
        }

        return $model;
    }

    /**
     * Get the model name.
     */
    public function getModel(): string
    {
        return $this->model;
    }
}
