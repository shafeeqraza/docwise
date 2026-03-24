<?php

namespace App\Domains\RAG\VectorStores\Concerns;

use App\Domains\RAG\Exceptions\QdrantException;
use App\Domains\RAG\Exceptions\VectorStoreException;
use App\Services\V1\Common\LogService;
use Illuminate\Http\Client\RequestException;

/**
 * Trait for retrying vector store operations with exponential backoff.
 *
 * Follows DRY principle: Shared retry logic reusable across vector store implementations.
 * 
 * Note: Classes using this trait must have a $logService property (injected via constructor).
 */
trait RetriesVectorStoreOperations
{
    /**
     * Retry a callable operation with exponential backoff.
     *
     * @param callable $operation The operation to retry
     * @param string $operationName The operation name (for logging)
     * @param int $maxRetries Maximum number of retry attempts
     * @param array $context Additional context for logging
     * @return mixed The result of the operation
     * @throws VectorStoreException|QdrantException
     */
    protected function retryWithBackoff(
        callable $operation,
        string $operationName,
        int $maxRetries = 3,
        array $context = []
    ) {
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                return $operation();
            } catch (RequestException | QdrantException | VectorStoreException $e) {
                $attempt++;

                if ($attempt >= $maxRetries) {
                    $this->logVectorStoreError(
                        "{$operationName} failed after {$maxRetries} retries",
                        $e,
                        array_merge($context, ['attempt' => $attempt])
                    );
                    throw ($e instanceof VectorStoreException || $e instanceof QdrantException)
                        ? $e
                        : new VectorStoreException("{$operationName} failed: {$e->getMessage()}", 0, $e);
                }

                $wait = pow(2, $attempt - 1); // Exponential backoff: 1s, 2s, 4s...
                $this->logVectorStoreWarning(
                    "{$operationName} error - retrying in {$wait}s",
                    $e,
                    array_merge($context, ['attempt' => $attempt, 'wait_seconds' => $wait])
                );

                sleep($wait);
            }
        }
    }

    /**
     * Log an error message.
     *
     * @param string $message The error message
     * @param \Exception $exception The exception
     * @param array $context Additional context
     * @return void
     */
    protected function logVectorStoreError(string $message, \Exception $exception, array $context = []): void
    {
        if ($this->logService) {
            $this->logService->error($message, array_merge([
                'error' => $exception->getMessage(),
            ], $context));
        }
    }

    /**
     * Log a warning message.
     *
     * @param string $message The warning message
     * @param \Exception $exception The exception
     * @param array $context Additional context
     * @return void
     */
    protected function logVectorStoreWarning(string $message, \Exception $exception, array $context = []): void
    {
        if ($this->logService) {
            $this->logService->warning($message, array_merge([
                'error' => $exception->getMessage(),
                'exception' => $exception,
            ], $context));
        }
    }
}
