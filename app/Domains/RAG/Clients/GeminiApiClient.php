<?php

namespace App\Domains\RAG\Clients;

use App\Domains\RAG\Exceptions\EmbeddingFailedException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Unified HTTP client for all Gemini API communication.
 *
 * Single Responsibility: Handles HTTP communication with Google's Gemini API.
 * Used by: GeminiTokenizer, GeminiEmbeddingProvider, and any other Gemini services.
 *
 * Features:
 * - Generic post() for any API endpoint
 * - Token counting (single and batch with concurrency)
 * - Testable via HttpFactory injection
 */
class GeminiApiClient
{
    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly int $timeout;
    private readonly array $headers;
    private readonly HttpFactory $http;

    /**
     * Create a new Gemini API client instance.
     *
     * @param HttpFactory|null $http HTTP client factory (optional, for testing)
     * @param string|null $apiKey API key override (defaults to config)
     * @param string|null $baseUrl Base URL override (defaults to config)
     * @param int|null $timeout Timeout override (defaults to config)
     * @throws EmbeddingFailedException If API key is not configured
     */
    public function __construct(
        ?HttpFactory $http = null,
        ?string $apiKey = null,
        ?string $baseUrl = null,
        ?int $timeout = null
    ) {
        $this->http = $http ?? app(HttpFactory::class);
        $this->apiKey = $apiKey ?? config('services.gemini.api_key');
        $this->baseUrl = $baseUrl ?? config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
        $this->timeout = $timeout ?? config('services.gemini.timeout', 60);

        if (empty($this->apiKey)) {
            throw new EmbeddingFailedException(
                'Gemini API key is required. Please set GEMINI_API_KEY environment variable.'
            );
        }

        $this->headers = [
            'x-goog-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Make a POST request to any Gemini API endpoint.
     *
     * @param string $path The API path (e.g., "models/gemini-embedding-001:embedContent")
     * @param array $data The request payload
     * @return Response The HTTP response
     * @throws EmbeddingFailedException If request fails
     */
    public function post(string $path, array $data = []): Response
    {
        $url = $this->buildUrl($path);

        try {
            $response = $this->http->timeout($this->timeout)
                ->withHeaders($this->headers)
                ->post($url, $data);

            if (!$response->successful()) {
                throw new EmbeddingFailedException(
                    "Gemini API request failed: {$response->status()} - {$response->body()}"
                );
            }

            return $response;
        } catch (RequestException $e) {
            throw new EmbeddingFailedException(
                "Gemini API request failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Count tokens for a single text.
     *
     * @param string $model The model name (with or without models/ prefix)
     * @param string $text The text to count tokens for
     * @return int Token count
     * @throws EmbeddingFailedException
     */
    public function countTokens(string $model, string $text): int
    {
        $modelName = $this->extractModelName($model);

        $response = $this->post(
            "models/{$modelName}:countTokens",
            $this->buildCountTokensPayload($model, $text)
        );

        return $this->parseTokenCountResponse($response);
    }

    /**
     * Count tokens for multiple texts using concurrent requests.
     *
     * @param string $model The model name (with or without models/ prefix)
     * @param array<int, string> $texts Array of texts keyed by index
     * @return array<int, int> Token counts keyed by index
     * @throws EmbeddingFailedException
     */
    public function countTokensBatch(string $model, array $texts): array
    {
        $modelName = $this->extractModelName($model);
        $url = $this->buildUrl("models/{$modelName}:countTokens");

        $responses = Http::pool(function (Pool $pool) use ($model, $texts, $url) {
            foreach ($texts as $index => $text) {
                $pool->as((string) $index)
                    ->timeout($this->timeout)
                    ->withHeaders($this->headers)
                    ->post($url, $this->buildCountTokensPayload($model, $text));
            }
        });

        return $this->parseBatchTokenResponses($responses, array_keys($texts));
    }

    /**
     * Build full URL from API path.
     */
    private function buildUrl(string $path): string
    {
        $path = ltrim($path, '/');

        if (str_starts_with($path, $this->baseUrl)) {
            return $path;
        }

        return "{$this->baseUrl}/{$path}";
    }

    /**
     * Build payload for countTokens request.
     */
    private function buildCountTokensPayload(string $model, string $text): array
    {
        return [
            'model' => $this->normalizeModelName($model),
            'contents' => [
                [
                    'parts' => [
                        ['text' => $text]
                    ]
                ]
            ]
        ];
    }

    /**
     * Parse token count from API response.
     *
     * @throws EmbeddingFailedException
     */
    private function parseTokenCountResponse(Response $response): int
    {
        $data = $response->json();
        $totalTokens = $data['totalTokens'] ?? null;

        if ($totalTokens === null || !is_int($totalTokens)) {
            throw new EmbeddingFailedException('Invalid token count response from Gemini API');
        }

        return $totalTokens;
    }

    /**
     * Parse batch responses and extract token counts.
     *
     * @throws EmbeddingFailedException
     */
    private function parseBatchTokenResponses(array $responses, array $indices): array
    {
        $results = [];

        foreach ($indices as $index) {
            $response = $responses[(string) $index];

            if (!$response->successful()) {
                throw new EmbeddingFailedException(
                    "Gemini API batch request failed for index {$index}: {$response->status()} - {$response->body()}"
                );
            }

            $results[$index] = $this->parseTokenCountResponse($response);
        }

        return $results;
    }

    /**
     * Normalize model name to ensure it has the "models/" prefix.
     */
    public function normalizeModelName(string $model): string
    {
        if (!str_starts_with($model, 'models/')) {
            return "models/{$model}";
        }

        return $model;
    }

    /**
     * Extract model name without "models/" prefix.
     */
    public function extractModelName(string $model): string
    {
        return str_replace('models/', '', $model);
    }

    /**
     * Get the configured timeout.
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Get the base URL.
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}
