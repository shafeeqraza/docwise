<?php

namespace App\Domains\RAG\Embeddings\Gemini;

use App\Domains\RAG\Attributes\EmbeddingDriver;
use App\Domains\RAG\Clients\GeminiApiClient;
use App\Domains\RAG\Contracts\EmbeddingProvider;
use App\Domains\RAG\DTOs\EmbeddingDTO;
use App\Domains\RAG\Embeddings\Concerns\RetriesEmbeddingRequests;
use App\Domains\RAG\Exceptions\EmbeddingFailedException;
use App\Services\V1\Common\LogService;

/**
 * Gemini embedding provider implementation.
 *
 * Follows Single Responsibility Principle (SRP): Only Gemini-specific embedding logic.
 * Follows Liskov Substitution Principle (LSP): Fully implements EmbeddingProvider interface.
 * Follows Open/Closed Principle (OCP): Can be extended without modification.
 * Follows Dependency Inversion Principle (DIP): Depends on GeminiApiClient abstraction.
 */
#[EmbeddingDriver('gemini')]
class GeminiEmbeddingProvider implements EmbeddingProvider
{
    use RetriesEmbeddingRequests;

    protected const DEFAULT_MODEL = 'models/gemini-embedding-001';
    protected const DEFAULT_BATCH_SIZE = 10;
    protected const DEFAULT_RETRIES = 5;
    protected const DEFAULT_BATCH_DELAY = 200000; // 0.2s in microseconds

    protected int $batchSize;
    protected int $maxRetries;

    /**
     * Create a new Gemini embedding provider instance.
     *
     * @param GeminiApiClient $client The Gemini API client
     * @param LogService|null $logService The log service
     */
    public function __construct(
        private readonly GeminiApiClient $client,
        ?LogService $logService = null
    ) {
        $this->batchSize = config('services.gemini.batch_size', self::DEFAULT_BATCH_SIZE);
        $this->maxRetries = config('services.gemini.max_retries', self::DEFAULT_RETRIES);

        // Set log service for trait
        $this->logService = $logService ?? app(LogService::class);
    }

    /**
     * Normalize model name to ensure it has the "models/" prefix.
     */
    protected function normalizeModelName(string $model): string
    {
        return $this->client->normalizeModelName($model);
    }

    /**
     * Get model name without "models/" prefix for endpoint URLs.
     */
    protected function getModelNameForEndpoint(string $model): string
    {
        return $this->client->extractModelName($model);
    }


    /**
     * Generate embedding for a single text.
     *
     * @param string $text The text to generate embedding for
     * @param string $model The embedding model to use
     * @return EmbeddingDTO The embedding DTO
     * @throws EmbeddingFailedException
     */
    #[\Override]
    public function generateEmbedding(string $text, string $model = self::DEFAULT_MODEL): EmbeddingDTO
    {
        $model = $this->normalizeModelName($model);

        return $this->retryWithBackoff(
            function () use ($text, $model) {
                $response = $this->makeEmbedContentRequest($text, $model);
                return $this->extractEmbeddingFromResponse($response, $model);
            },
            $model,
            'Gemini',
            $this->maxRetries,
            1
        );
    }

    /**
     * Generate embeddings for multiple texts in batch using batchEmbedContents endpoint.
     *
     * @param array<string> $texts Array of texts to generate embeddings for
     * @param string $model The embedding model to use
     * @return array<EmbeddingDTO> Array of embedding DTOs
     * @throws EmbeddingFailedException
     */
    #[\Override]
    public function generateEmbeddingsBatch(array $texts, string $model = self::DEFAULT_MODEL): array
    {
        if (empty($texts)) {
            return [];
        }

        $model = $this->normalizeModelName($model);
        $allEmbeddings = [];

        foreach (array_chunk($texts, $this->batchSize) as $batch) {
            $embeddings = $this->processBatchWithRetry($batch, $model);
            $allEmbeddings = array_merge($allEmbeddings, $embeddings);

            $this->delayBetweenBatches(count($texts), $this->batchSize, self::DEFAULT_BATCH_DELAY);
        }

        return $allEmbeddings;
    }

    /**
     * Process a batch of texts with retry logic.
     *
     * @param array<string> $batch Array of texts in the batch
     * @param string $model The model name
     * @return array<EmbeddingDTO> Array of embedding DTOs
     * @throws EmbeddingFailedException
     */
    protected function processBatchWithRetry(array $batch, string $model): array
    {
        return $this->retryWithBackoff(
            function () use ($batch, $model) {
                return $this->processBatch($batch, $model);
            },
            $model,
            'Gemini',
            $this->maxRetries,
            count($batch)
        );
    }

    /**
     * Process a single batch of texts.
     *
     * @param array<string> $batch Array of texts in the batch
     * @param string $model The model name
     * @return array<EmbeddingDTO> Array of embedding DTOs
     * @throws EmbeddingFailedException
     */
    protected function processBatch(array $batch, string $model): array
    {
        $requests = $this->prepareBatchRequests($batch, $model);
        $response = $this->makeBatchEmbedContentsRequest($requests, $model);
        return $this->extractEmbeddingsFromBatchResponse($response, count($batch), $model);
    }

    /**
     * Prepare batch requests array from texts.
     *
     * @param array<string> $texts Array of texts
     * @param string $model The model name
     * @return array Array of request objects
     */
    protected function prepareBatchRequests(array $texts, string $model): array
    {
        return array_map(function ($text) use ($model) {
            return [
                'model' => $model,
                'content' => [
                    'parts' => [
                        [
                            'text' => $text
                        ]
                    ]
                ],
                'output_dimensionality' => 1536
            ];
        }, $texts);
    }

    /**
     * Make embedContent API request for a single text.
     *
     * @param string $text The text to embed
     * @param string $model The model name
     * @return \Illuminate\Http\Client\Response
     * @throws EmbeddingFailedException
     */
    protected function makeEmbedContentRequest(string $text, string $model): \Illuminate\Http\Client\Response
    {
        return $this->client->post("{$model}:embedContent", [
            'model' => $model,
            'content' => [
                'parts' => [
                    [
                        'text' => $text
                    ]
                ]
            ],
            'output_dimensionality' => 1536
        ]);
    }

    /**
     * Make batchEmbedContents API request.
     *
     * @param array $requests Array of request objects
     * @param string $model The model name
     * @return \Illuminate\Http\Client\Response
     * @throws EmbeddingFailedException
     */
    protected function makeBatchEmbedContentsRequest(array $requests, string $model): \Illuminate\Http\Client\Response
    {
        $modelName = $this->getModelNameForEndpoint($model);

        return $this->client->post("models/{$modelName}:batchEmbedContents", [
            'requests' => $requests
        ]);
    }

    /**
     * Extract embedding vector from single embedContent response.
     *
     * @param \Illuminate\Http\Client\Response $response The API response
     * @param string $model The model name
     * @return EmbeddingDTO The embedding DTO
     * @throws EmbeddingFailedException
     */
    protected function extractEmbeddingFromResponse(\Illuminate\Http\Client\Response $response, string $model): EmbeddingDTO
    {
        $data = $response->json();
        $vector = $data['embedding']['values'] ?? null;

        if (!$vector || !is_array($vector)) {
            throw new EmbeddingFailedException('Invalid embedding response from Gemini API');
        }

        $dimension = $this->getEmbeddingDimension($model);
        return new EmbeddingDTO($vector, $model, $dimension);
    }

    /**
     * Extract embedding vectors from batch response.
     *
     * @param \Illuminate\Http\Client\Response $response The API response
     * @param int $expectedCount Expected number of embeddings
     * @param string $model The model name
     * @return array<EmbeddingDTO> Array of embedding DTOs
     * @throws EmbeddingFailedException
     */
    protected function extractEmbeddingsFromBatchResponse(\Illuminate\Http\Client\Response $response, int $expectedCount, string $model): array
    {
        $data = $response->json();
        $embeddings = $data['embeddings'] ?? [];

        if (count($embeddings) !== $expectedCount) {
            throw new EmbeddingFailedException(
                "Mismatch in embedding count. Expected: {$expectedCount}, Got: " . count($embeddings)
            );
        }

        $dimension = $this->getEmbeddingDimension($model);
        $result = [];
        foreach ($embeddings as $embeddingData) {
            $vector = $embeddingData['values'] ?? null;
            if (!is_array($vector)) {
                throw new EmbeddingFailedException('Invalid embedding response from Gemini batch API');
            }
            $result[] = new EmbeddingDTO($vector, $model, $dimension);
        }

        return $result;
    }

    /**
     * Get embedding dimension for Gemini models.
     *
     * @param string $model The embedding model name
     * @return int The dimension of the embedding vector
     */
    #[\Override]
    public function getEmbeddingDimension(string $model = self::DEFAULT_MODEL): int
    {
        $model = $this->normalizeModelName($model);

        // Gemini embedding-001 model with output_dimensionality=1536 returns 1536-dimensional vectors
        return match ($model) {
            self::DEFAULT_MODEL => 1536,
            default => 1536,
        };
    }

    /**
     * Check if this provider supports the given model.
     *
     * @param string $model The embedding model name
     * @return bool True if the provider supports the model
     */
    #[\Override]
    public function supports(string $model): bool
    {
        $normalizedModel = $this->normalizeModelName($model);

        // Check if model name contains 'gemini' or matches known Gemini models
        return str_contains(strtolower($normalizedModel), 'gemini') ||
            str_contains(strtolower($normalizedModel), 'embedding-001');
    }
}
