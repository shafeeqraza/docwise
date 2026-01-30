<?php

namespace App\Domains\RAG\Tokenizers\Gemini;

use App\Domains\RAG\Exceptions\EmbeddingFailedException;
use App\Domains\RAG\Tokenizers\TokenizerInterface;
use Illuminate\Http\Client\ConnectionException;
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
    protected const MAX_RETRIES = 3;
    protected const CONNECT_TIMEOUT = 10; // Separate timeout for SSL connection

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

        return $this->retryWithBackoff(
            function () use ($modelName, $text) {
                try {
                    $response = Http::timeout($this->timeout)
                        ->connectTimeout(self::CONNECT_TIMEOUT)
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
                } catch (RequestException | ConnectionException $e) {
                    throw new EmbeddingFailedException("Failed to count tokens via Gemini API: {$e->getMessage()}", 0, $e);
                }
            },
            self::MAX_RETRIES
        );
    }

    /**
     * Retry a callable operation with exponential backoff.
     *
     * @param callable $operation The operation to retry
     * @param int $maxRetries Maximum number of retry attempts
     * @return mixed The result of the operation
     * @throws EmbeddingFailedException
     */
    protected function retryWithBackoff(callable $operation, int $maxRetries = self::MAX_RETRIES): mixed
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                return $operation();
            } catch (EmbeddingFailedException $e) {
                $lastException = $e;
                $attempt++;

                $previousException = $e->getPrevious();

                // Check if this is a retryable error (connection/timeout issues)
                $isRetryable = $this->isRetryableError($previousException, $e->getMessage());

                // Don't retry on non-connection errors (e.g., API errors, invalid responses)
                if (!$isRetryable) {
                    throw $e;
                }

                if ($attempt >= $maxRetries) {
                    throw new EmbeddingFailedException(
                        "Failed to count tokens after {$maxRetries} retries: {$e->getMessage()}",
                        0,
                        $e
                    );
                }

                $this->waitBeforeRetry($attempt);
            } catch (ConnectionException | RequestException $e) {
                // Handle direct exceptions (not wrapped in EmbeddingFailedException)
                $lastException = $e;
                $attempt++;

                $isRetryable = $this->isRetryableError($e, $e->getMessage());

                if (!$isRetryable || $attempt >= $maxRetries) {
                    throw new EmbeddingFailedException(
                        "Failed to count tokens via Gemini API: {$e->getMessage()}",
                        0,
                        $e
                    );
                }

                $this->waitBeforeRetry($attempt);
            }
        }

        if ($lastException) {
            throw new EmbeddingFailedException(
                "Failed to count tokens after {$maxRetries} retries: {$lastException->getMessage()}",
                0,
                $lastException
            );
        }

        throw new EmbeddingFailedException('Failed to count tokens: max retries exceeded');
    }

    /**
     * Check if an error is retryable (connection/timeout issues).
     *
     * @param \Exception|null $exception The exception to check
     * @param string $message The error message
     * @return bool True if the error is retryable
     */
    protected function isRetryableError(?\Exception $exception, string $message): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if ($exception instanceof RequestException) {
            return str_contains($message, 'timeout') ||
                   str_contains($message, 'SSL connection') ||
                   str_contains($message, 'Connection');
        }

        return false;
    }

    /**
     * Wait before retrying with exponential backoff.
     *
     * @param int $attempt The current attempt number
     * @return void
     */
    protected function waitBeforeRetry(int $attempt): void
    {
        $wait = pow(2, $attempt - 1); // Exponential backoff: 1s, 2s, 4s...
        sleep($wait);
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
