<?php

namespace App\Domains\RAG\Embeddings\Gemini;

use App\Domains\RAG\Exceptions\EmbeddingFailedException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;

/**
 * Gemini API HTTP client wrapper.
 *
 * Handles all HTTP communication with Gemini API, including
 * URL building, authentication, and request methods.
 *
 * This class is stateful and stores configuration values
 * (API key, base URL, timeout) as instance properties.
 */
class Gemini
{
    /**
     * The Gemini API key.
     *
     * @var string
     */
    private readonly string $apiKey;

    /**
     * The Gemini API base URL.
     *
     * @var string
     */
    private readonly string $baseUrl;

    /**
     * The request timeout in seconds.
     *
     * @var int
     */
    private readonly int $timeout;

    /**
     * HTTP headers for API requests.
     *
     * @var array<string, string>
     */
    private readonly array $headers;

    /**
     * Create a new Gemini HTTP client instance.
     *
     * Reads configuration from environment/config and validates required values.
     *
     * @param HttpFactory $http The HTTP client factory
     * @param string|null $apiKey Optional API key override (defaults to GEMINI_API_KEY env)
     * @param string|null $baseUrl Optional base URL override (defaults to GEMINI_BASE_URL env)
     * @param int|null $timeout Optional timeout override (defaults to GEMINI_TIMEOUT env)
     * @throws EmbeddingFailedException If API key is not configured or timeout is invalid
     */
    public function __construct(
        private readonly HttpFactory $http,
        ?string $apiKey = null,
        ?string $baseUrl = null,
        ?int $timeout = null
    ) {
        // Read from config/env, allow constructor override for testing
        $apiKey = $apiKey ?? config('services.gemini.api_key');
        $baseUrl = $baseUrl ?? config('services.gemini.base_url');
        $timeout = $timeout ?? config('services.gemini.timeout');

        // Validate required configuration
        if (empty($apiKey)) {
            throw new EmbeddingFailedException(
                'Gemini API key is required. Please set GEMINI_API_KEY environment variable.'
            );
        }

        // Set readonly properties after validation
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
        $this->timeout = $timeout;

        // Build headers once
        $this->headers = [
            'x-goog-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Make a POST request to Gemini API.
     *
     * @param string $path The API path (e.g., "models/gemini-embedding-001:embedContent")
     * @param array $data The request payload
     * @return Response The HTTP response
     * @throws EmbeddingFailedException If request fails
     */
    public function post(string $path, array $data = []): Response
    {
        $url = $this->buildUrl($path);

        $response = $this->http->timeout($this->timeout)
            ->withHeaders($this->headers)
            ->post($url, $data);

        if (!$response->successful()) {
            throw new EmbeddingFailedException(
                "Gemini API request failed: {$response->status()} - {$response->body()}"
            );
        }

        return $response;
    }

    /**
     * Build Gemini API URL from path.
     *
     * @param string $path The API path
     * @return string The complete URL
     */
    private function buildUrl(string $path): string
    {
        // Remove leading slash if present to avoid double slashes
        $path = ltrim($path, '/');

        // If path already starts with baseUrl, return as is
        if (str_starts_with($path, $this->baseUrl)) {
            return $path;
        }

        return "{$this->baseUrl}/{$path}";
    }
}
