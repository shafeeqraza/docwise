<?php

namespace App\Domains\RAG\VectorStores\Qdrant;

use App\Domains\RAG\Exceptions\QdrantException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Qdrant HTTP client wrapper.
 *
 * Handles all HTTP communication with Qdrant API, including
 * URL building, authentication, and request methods.
 */
class Qdrant
{
    /**
     * Build a full request URL (for logging/debugging).
     */
    public function buildRequestUrl(string $path): string
    {
        return $this->buildUrl($path);
    }

    /**
     * Make a GET request to Qdrant API.
     *
     * @param string $path The API path (e.g., "/collections/documents")
     * @return Response The HTTP response
     * @throws QdrantException If request fails
     */
    public function get(string $path): Response
    {
        $url = $this->buildUrl($path);
        $headers = $this->getHeaders();

        $response = Http::withHeaders($headers)->get($url);

        if (!$response->successful()) {
            throw new QdrantException('Qdrant GET request failed: ' . $response->body());
        }

        return $response;
    }

    /**
     * Make a POST request to Qdrant API.
     *
     * @param string $path The API path
     * @param array $data The request payload
     * @return Response The HTTP response
     * @throws QdrantException If request fails
     */
    public function post(string $path, array $data = []): Response
    {
        $url = $this->buildUrl($path);
        $timeout = (int) config('qdrant.timeout', 30);
        $headers = $this->getHeaders();

        // Use Laravel's JSON request mode (sets Content-Type/Accept correctly).
        // This keeps the client simple; vector formatting is handled upstream.
        $response = Http::withHeaders($headers)
            ->timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->post($url, $data);

        if (!$response->successful()) {
            throw new QdrantException(
                'Qdrant POST request failed: ' . $response->body() .
                    ' | Status: ' . $response->status() .
                    ' | URL: ' . $url .
                    ' | Request Data: ' . json_encode($data)
            );
        }

        return $response;
    }

    /**
     * Make a PUT request to Qdrant API.
     *
     * @param string $path The API path
     * @param array $data The request payload
     * @return Response The HTTP response
     * @throws QdrantException If request fails
     */
    public function put(string $path, array $data = []): Response
    {
        $url = $this->buildUrl($path);
        $headers = $this->getHeaders();

        $response = Http::withHeaders($headers)->put($url, $data);

        if (!$response->successful()) {
            throw new QdrantException('Qdrant PUT request failed: ' . $response->body());
        }

        return $response;
    }

    /**
     * Make a GET request without throwing exception on failure.
     * Useful for checking if collection exists.
     *
     * @param string $path The API path
     * @return Response The HTTP response
     */
    public function getWithoutException(string $path): Response
    {
        $url = $this->buildUrl($path);
        $headers = $this->getHeaders();

        return Http::withHeaders($headers)->get($url);
    }

    /**
     * Make a PUT request without throwing exception on failure.
     * Useful for operations that may fail gracefully.
     *
     * @param string $path The API path
     * @param array $data The request payload
     * @return Response The HTTP response
     */
    public function putWithoutException(string $path, array $data = []): Response
    {
        $url = $this->buildUrl($path);
        $headers = $this->getHeaders();

        return Http::withHeaders($headers)->put($url, $data);
    }

    /**
     * Build Qdrant URL, handling both localhost and cloud instances.
     *
     * @param string $path The API path
     * @return string The complete URL
     */
    private function buildUrl(string $path): string
    {
        $host = (string) config('qdrant.host', 'localhost');
        $port = (int) config('qdrant.port', 6333);

        // Allow full base URL in env, e.g. https://xxx.qdrant.tech:6333
        if (str_starts_with($host, 'http://') || str_starts_with($host, 'https://')) {
            // If a scheme is provided but no port, append configured port (needed for local Qdrant: :6333)
            $parsed = parse_url($host);
            $scheme = $parsed['scheme'] ?? null;
            $parsedHost = $parsed['host'] ?? null;
            $parsedPort = $parsed['port'] ?? null;

            if ($scheme && $parsedHost && !$parsedPort) {
                $base = "{$scheme}://{$parsedHost}:{$port}";
                return $base . $path;
            }

            return rtrim($host, '/') . $path;
        }

        // Determine protocol: use https for cloud instances, http for localhost.
        $scheme = ($host === 'localhost' || $host === '127.0.0.1') ? 'http' : 'https';

        // If host already includes a port, don't append config port again.
        $base = str_contains($host, ':')
            ? "{$scheme}://{$host}"
            : "{$scheme}://{$host}:{$port}";

        return $base . $path;
    }

    /**
     * Get HTTP headers including API key if configured.
     *
     * @return array<string, string> Headers array
     */
    private function getHeaders(): array
    {
        $apiKey = config('qdrant.api_key');

        return $apiKey ? ['api-key' => $apiKey] : [];
    }
}
