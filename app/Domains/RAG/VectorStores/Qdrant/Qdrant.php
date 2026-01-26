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
        $headers = $this->getHeaders();

        $response = Http::withHeaders($headers)->post($url, $data);

        if (!$response->successful()) {
            throw new QdrantException('Qdrant POST request failed: ' . $response->body());
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
        $host = config('qdrant.host', 'localhost');
        $port = config('qdrant.port', 6333);

        // Remove any existing protocol from host
        $host = preg_replace('#^https?://#', '', $host);

        // Determine protocol: use https for cloud instances, http for localhost
        $protocol = ($host !== 'localhost' && $host !== '127.0.0.1') ? 'https' : 'http';

        // For HTTPS (cloud), don't include port (uses default 443)
        // For HTTP (localhost), include port
        if ($protocol === 'https') {
            return "https://{$host}{$path}";
        } else {
            return "http://{$host}:{$port}{$path}";
        }
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
