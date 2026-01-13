<?php

namespace App\Domains\RAG\Embeddings\Concerns;

use App\Domains\RAG\Exceptions\EmbeddingFailedException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * Trait for retrying embedding requests with exponential backoff.
 *
 * Follows DRY principle: Shared retry logic reusable across embedding providers.
 */
trait RetriesEmbeddingRequests
{
    /**
     * Retry a callable operation with exponential backoff.
     *
     * @param callable $operation The operation to retry
     * @param string $model The model name (for logging)
     * @param string $providerName The provider name (for logging)
     * @param int $maxRetries Maximum number of retry attempts
     * @param int $batchSize Batch size (for logging)
     * @return mixed The result of the operation
     * @throws EmbeddingFailedException
     */
    protected function retryWithBackoff(
        callable $operation,
        string $model,
        string $providerName,
        int $maxRetries,
        int $batchSize = 1
    ) {
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                return $operation();
            } catch (RequestException | EmbeddingFailedException $e) {
                $attempt++;

                if ($attempt >= $maxRetries) {
                    $this->logError(
                        "{$providerName} embedding generation failed after retries",
                        $model,
                        $e,
                        ['batch_size' => $batchSize, 'attempt' => $attempt]
                    );
                    throw $e instanceof EmbeddingFailedException
                        ? $e
                        : new EmbeddingFailedException("Failed to generate {$providerName} embeddings: {$e->getMessage()}", 0, $e);
                }

                $wait = pow(2, $attempt - 1); // Exponential backoff: 1s, 2s, 4s, 8s...
                $this->logWarning(
                    "{$providerName} API error - retrying in {$wait}s",
                    $model,
                    $e,
                    [
                        'batch_size' => $batchSize,
                        'attempt' => $attempt,
                    ]
                );

                sleep($wait);
            }
        }
    }

    /**
     * Add delay between batches if processing multiple batches.
     *
     * @param int $totalTexts Total number of texts being processed
     * @param int $batchSize Batch size
     * @param int $delayMicroseconds Delay in microseconds
     * @return void
     */
    protected function delayBetweenBatches(int $totalTexts, int $batchSize, int $delayMicroseconds): void
    {
        if ($totalTexts > $batchSize) {
            usleep($delayMicroseconds);
        }
    }

    /**
     * Log an error message.
     *
     * @param string $message The error message
     * @param string $model The model name
     * @param \Exception $exception The exception
     * @param array $context Additional context
     * @return void
     */
    protected function logError(string $message, string $model, \Exception $exception, array $context = []): void
    {
        Log::error($message, array_merge([
            'model' => $model,
            'error' => $exception->getMessage(),
        ], $context));
    }

    /**
     * Log a warning message.
     *
     * @param string $message The warning message
     * @param string $model The model name
     * @param \Exception $exception The exception
     * @param array $context Additional context
     * @return void
     */
    protected function logWarning(string $message, string $model, \Exception $exception, array $context = []): void
    {
        Log::warning($message, array_merge([
            'model' => $model,
            'error' => $exception->getMessage(),
            'exception' => $exception,
        ], $context));
    }
}
