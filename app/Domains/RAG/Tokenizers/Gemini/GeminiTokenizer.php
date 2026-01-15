<?php

namespace App\Domains\RAG\Tokenizers\Gemini;

use App\Domains\RAG\Exceptions\EmbeddingFailedException;
use App\Domains\RAG\Tokenizers\TokenizerInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Gemini tokenizer for counting tokens using Gemini API.
 *
 * Follows Single Responsibility Principle (SRP): Only token counting logic.
 * Follows Liskov Substitution Principle (LSP): Fully implements TokenizerInterface.
 */
class GeminiTokenizer implements TokenizerInterface
{
    protected const DEFAULT_MODEL = 'models/gemini-embedding-001';

    protected string $apiKey;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
    protected int $timeout;
    protected string $model;

    /**
     * Create a new GeminiTokenizer instance.
     *
     * @param string $model The Gemini model name (e.g., 'gemini-embedding-001', 'models/gemini-embedding-001')
     */
    public function __construct(string $model = self::DEFAULT_MODEL)
    {
        $this->model = $this->normalizeModelName($model);
        $this->apiKey = config('services.gemini.api_key');

        if (!$this->apiKey) {
            throw new EmbeddingFailedException('Gemini API key not configured');
        }

        $this->timeout = config('services.gemini.timeout', 60);
    }

    /**
     * Normalize model name to ensure it has the "models/" prefix.
     *
     * @param string $model The model name
     * @return string Normalized model name
     */
    protected function normalizeModelName(string $model): string
    {
        if (!str_starts_with($model, 'models/')) {
            return "models/{$model}";
        }
        return $model;
    }

    /**
     * Get model name without "models/" prefix for endpoint URLs.
     *
     * @param string $model The model name
     * @return string Model name without prefix
     */
    protected function getModelNameForEndpoint(string $model): string
    {
        return str_replace('models/', '', $model);
    }

    /**
     * Count tokens in text using Gemini API countTokens endpoint.
     *
     * @param string $text The text to count tokens for
     * @return int Number of tokens
     * @throws EmbeddingFailedException If token counting fails
     */
    public function countTokens(string $text): int
    {
        if (empty($text)) {
            return 0;
        }

        return $this->countTokensViaApi($text);
    }

    /**
     * Count tokens using Gemini API countTokens endpoint.
     *
     * @param string $text The text to count tokens for
     * @return int Number of tokens
     * @throws EmbeddingFailedException
     */
    protected function countTokensViaApi(string $text): int
    {
        $modelName = $this->getModelNameForEndpoint($this->model);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'x-goog-api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/models/{$modelName}:countTokens", [
                    'model' => $this->model,
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => $text
                                ]
                            ]
                        ]
                    ]
                ]);

            if (!$response->successful()) {
                throw new EmbeddingFailedException(
                    "Gemini countTokens API request failed: {$response->status()} - {$response->body()}"
                );
            }

            $data = $response->json();
            $totalTokens = $data['totalTokens'] ?? null;

            if ($totalTokens === null || !is_int($totalTokens)) {
                throw new EmbeddingFailedException('Invalid token count response from Gemini API');
            }

            return $totalTokens;
        } catch (RequestException $e) {
            throw new EmbeddingFailedException("Failed to count tokens via Gemini API: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get text length in tokens (for chunking purposes).
     *
     * @param string $text The text to measure
     * @return int Token length
     */
    public function getTokenLength(string $text): int
    {
        return $this->countTokens($text);
    }
}
