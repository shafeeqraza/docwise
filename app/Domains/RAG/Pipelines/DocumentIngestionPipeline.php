<?php

namespace App\Domains\RAG\Pipelines;

use App\Domains\RAG\Contracts\VectorStore;
use App\Domains\RAG\DTOs\ChunkDTO;
use App\Domains\RAG\DTOs\DocumentDTO;
use App\Domains\RAG\Exceptions\EmbeddingFailedException;
use App\Domains\RAG\Exceptions\TextExtractionException;
use App\Domains\RAG\Exceptions\VectorStoreException;
use App\Domains\RAG\Factories\DocumentLoaderFactory;
use App\Domains\RAG\Factories\EmbeddingProviderFactory;
use App\Domains\RAG\Services\TextChunkingService;
use App\Domains\RAG\Services\UsageMetricService;
use App\Domains\RAG\Validators\TokenLimitValidator;
use App\Repositories\V1\Contracts\DocumentChunkRepositoryInterface;
use App\Services\V1\Common\LogService;

/**
 * Document ingestion pipeline that orchestrates the RAG workflow.
 *
 * Pipeline: Load → Split → Embed → Store
 *
 * Follows Single Responsibility Principle (SRP): Only orchestration logic, no business rules.
 * Follows Dependency Inversion Principle (DIP): Depends on interfaces, not concrete classes.
 * Follows Open/Closed Principle (OCP): Easy to extend pipeline steps without modification.
 */
class DocumentIngestionPipeline
{
    private int $lastBenchmarkDuration = 0;

    public function __construct(
        private DocumentLoaderFactory $loaderFactory,
        private TextChunkingService $chunkingService,
        private EmbeddingProviderFactory $embeddingProviderFactory,
        private VectorStore $vectorStore,
        private DocumentChunkRepositoryInterface $chunkRepository,
        private TokenLimitValidator $tokenLimitValidator,
        private UsageMetricService $usageMetricService,
        private LogService $logService
    ) {}

    /**
     * Process a document through the RAG pipeline.
     *
     * @param DocumentDTO $document The document to process
     * @param array<string, mixed> $options Processing options (e.g., embedding_model, chunk_size, skip_vector_storage)
     * @return array<ChunkDTO> Array of processed chunks with embeddings
     * @throws TextExtractionException If document loading fails
     * @throws EmbeddingFailedException If embedding generation fails
     * @throws \RuntimeException If any pipeline step fails
     */
    public function process(DocumentDTO $document, array $options = []): array
    {
        $totalSteps = 8;
        $currentStep = 0;
        $overallStartTime = microtime(true);
        $stepTimings = [];

        // Step 1: Load document text
        $this->updateProgress($options, ++$currentStep, $totalSteps, 'Loading document');
        $text = $this->benchmarkStep('Loading document', function() use ($document) {
            return $this->loadDocument($document);
        }, ['file_type' => $document->fileType->value]);
        $stepTimings['Loading document'] = $this->getLastBenchmarkDuration();

        // Step 2: Chunk text using TextChunkingService (returns ChunkDTOs without IDs)
        $this->updateProgress($options, ++$currentStep, $totalSteps, 'Chunking text');
        $chunkDTOs = $this->benchmarkStep('Chunking text', function() use ($text, $document, $options) {
            return $this->chunkingService->chunkText($text, $document, $options);
        }, ['text_length' => strlen($text)]);
        $stepTimings['Chunking text'] = $this->getLastBenchmarkDuration();
        $chunkCount = count($chunkDTOs);

        // Step 3: Persist chunks to MySQL and update DTOs with IDs and UUIDs
        $this->updateProgress($options, ++$currentStep, $totalSteps, 'Persisting chunks');
        $chunkDTOs = $this->benchmarkStep('Persisting chunks', function() use ($chunkDTOs, $options) {
            return $this->persistChunksAndUpdateDTOs($chunkDTOs, $options);
        }, ['chunk_count' => $chunkCount]);
        $stepTimings['Persisting chunks'] = $this->getLastBenchmarkDuration();

        // Step 4: Validate token limit before generating embeddings
        $this->updateProgress($options, ++$currentStep, $totalSteps, 'Validating token limits');
        $companyId = $options['company_id'] ?? null;
        if ($companyId) {
            $totalTokens = array_sum(array_map(fn($chunk) => $chunk->tokens, $chunkDTOs));
            $this->benchmarkStep('Validating token limits', function() use ($companyId, $chunkDTOs) {
                $this->tokenLimitValidator->validate($companyId, $chunkDTOs);
            }, ['total_tokens' => $totalTokens]);
            $stepTimings['Validating token limits'] = $this->getLastBenchmarkDuration();
        } else {
            $stepTimings['Validating token limits'] = 0;
        }

        // Step 5: Generate embeddings for chunks
        $this->updateProgress($options, ++$currentStep, $totalSteps, 'Generating embeddings');
        $embeddingModel = $options['embedding_model'] ?? 'models/gemini-embedding-001';
        $embeddings = $this->benchmarkStep('Generating embeddings', function() use ($chunkDTOs, $options) {
            return $this->generateEmbeddings($chunkDTOs, $options);
        }, ['chunk_count' => $chunkCount, 'embedding_model' => $embeddingModel]);
        $stepTimings['Generating embeddings'] = $this->getLastBenchmarkDuration();

        $chunksWithEmbeddings = $this->benchmarkStep('Map embeddings to chunks', function() use ($chunkDTOs, $embeddings) {
            return $this->mapEmbeddingsToChunks($chunkDTOs, $embeddings);
        }, ['chunk_count' => $chunkCount]);
        $stepTimings['Map embeddings to chunks'] = $this->getLastBenchmarkDuration();

        // Step 6: Store vectors in vector store
        $this->updateProgress($options, ++$currentStep, $totalSteps, 'Storing vectors');
        $vectorDimension = !empty($chunksWithEmbeddings) ? ($chunksWithEmbeddings[0]->embedding?->dimension ?? 1536) : 1536;
        $this->benchmarkStep('Storing vectors', function() use ($chunksWithEmbeddings) {
            $this->storeVectors($chunksWithEmbeddings);
        }, ['chunk_count' => $chunkCount, 'vector_dimension' => $vectorDimension]);
        $stepTimings['Storing vectors'] = $this->getLastBenchmarkDuration();

        // Step 7: Update persisted chunks with vector store IDs when required (e.g. Qdrant)
        $this->updateProgress($options, ++$currentStep, $totalSteps, 'Updating metadata');
        $this->benchmarkStep('Updating metadata', function() use ($chunksWithEmbeddings, $options) {
            $this->updateChunksWithVectorStoreIds($chunksWithEmbeddings, $options);
        }, ['chunk_count' => $chunkCount]);
        $stepTimings['Updating metadata'] = $this->getLastBenchmarkDuration();

        // Step 8: Update usage metrics
        $this->updateProgress($options, ++$currentStep, $totalSteps, 'Recording metrics');
        $companyId = $options['company_id'] ?? null;
        if ($companyId) {
            $this->benchmarkStep('Recording metrics', function() use ($companyId, $chunksWithEmbeddings) {
                $this->usageMetricService->recordDocumentProcessing($companyId, $chunksWithEmbeddings);
            }, ['chunk_count' => $chunkCount]);
            $stepTimings['Recording metrics'] = $this->getLastBenchmarkDuration();
        } else {
            $stepTimings['Recording metrics'] = 0;
        }

        // Step 9: Call completion callback if provided (for updating version, etc.)
        if (isset($options['on_complete']) && is_callable($options['on_complete'])) {
            $options['on_complete'](count($chunksWithEmbeddings), $options['embedding_model'] ?? null);
        }

        // Final progress update
        $this->updateProgress($options, $totalSteps, $totalSteps, 'Completed');

        // Log summary with total time and breakdown
        $totalDurationMs = (int) ((microtime(true) - $overallStartTime) * 1000);
        $this->logService->info("DocumentIngestionPipeline: Processing completed", [
            'total_duration_ms' => $totalDurationMs,
            'step_breakdown' => $stepTimings,
            'document_id' => $document->id,
            'chunk_count' => $chunkCount,
            'embedding_model' => $embeddingModel,
        ]);

        return $chunksWithEmbeddings;
    }

    /**
     * Update progress tracking for the ingestion job.
     *
     * @param array<string, mixed> $options Processing options containing progress callback
     * @param int $currentStep Current step number
     * @param int $totalSteps Total number of steps
     * @param string $status Current status message
     * @return void
     */
    private function updateProgress(array $options, int $currentStep, int $totalSteps, string $status): void
    {
        if (isset($options['on_progress']) && is_callable($options['on_progress'])) {
            $percentage = (int) (($currentStep / $totalSteps) * 100);
            $options['on_progress']($percentage, $status, $currentStep, $totalSteps);
        }
    }

    /**
     * Benchmark a pipeline step execution and log timing information.
     *
     * @param string $stepName Name of the step being benchmarked
     * @param callable $callback The step execution callback
     * @param array<string, mixed> $metadata Additional metadata to log
     * @return mixed The result of the callback execution
     */
    private function benchmarkStep(string $stepName, callable $callback, array $metadata = []): mixed
    {
        $startTime = microtime(true);
        $result = $callback();
        $durationMs = (int) ((microtime(true) - $startTime) * 1000);
        
        $this->lastBenchmarkDuration = $durationMs;
        
        $this->logService->info("DocumentIngestionPipeline: Step '{$stepName}' completed", [
            'step' => $stepName,
            'duration_ms' => $durationMs,
            'metadata' => $metadata,
        ]);
        
        return $result;
    }

    /**
     * Get the duration of the last benchmarked step.
     *
     * @return int Duration in milliseconds
     */
    private function getLastBenchmarkDuration(): int
    {
        return $this->lastBenchmarkDuration;
    }

    /**
     * Load document text using appropriate loader.
     *
     * @param DocumentDTO $document The document to load
     * @return string The extracted text
     * @throws TextExtractionException If loading fails
     */
    private function loadDocument(DocumentDTO $document): string
    {
        try {
            $loader = $this->loaderFactory->create(fileType: $document->fileType);
            return $loader->load($document->content); // content may be file path/URL
        } catch (\Exception $e) {
            throw new TextExtractionException(
                "Failed to load document: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Persist chunks to MySQL and update ChunkDTOs with IDs and UUIDs.
     *
     * @param array<ChunkDTO> $chunkDTOs Array of ChunkDTOs (without IDs)
     * @param array<string, mixed> $options Processing options
     * @return array<ChunkDTO> Array of ChunkDTOs with IDs and UUIDs updated
     */
    private function persistChunksAndUpdateDTOs(array $chunkDTOs, array $options): array
    {
        if (empty($chunkDTOs)) {
            return [];
        }

        $embeddingModel = $options['embedding_model'] ?? 'models/gemini-embedding-001';

        // Prepare chunk data for persistence from DTOs
        $chunksData = [];
        foreach ($chunkDTOs as $chunkDTO) {
            $chunksData[] = [
                'company_id' => $chunkDTO->companyId,
                'document_id' => $chunkDTO->documentId,
                'version_id' => $chunkDTO->versionId,
                'chunk_index' => $chunkDTO->index,
                'content' => $chunkDTO->content,
                'token_count' => $chunkDTO->tokens,
                'embedding_model' => $embeddingModel,
                'metadata' => $chunkDTO->metadata,
            ];
        }

        // Persist chunks to MySQL
        $persistedChunks = $this->chunkRepository->createBatch($chunksData);

        // Update ChunkDTOs with IDs and UUIDs from persisted chunks
        $updatedDTOs = [];
        foreach ($persistedChunks as $index => $chunk) {
            $originalDTO = $chunkDTOs[$index];

            // Store UUID in metadata for use as vector ID
            $metadata = $originalDTO->metadata;
            $metadata['uuid'] = $chunk->uuid;

            // Create updated DTO with ID and UUID in metadata
            $updatedDTOs[] = $originalDTO->withId($chunk->id)->withMetadata($metadata);
        }

        return $updatedDTOs;
    }

    /**
     * Generate embeddings for chunks.
     *
     * @param array<ChunkDTO> $chunks Array of chunks
     * @param array<string, mixed> $options Processing options
     * @return array<ChunkDTO> Array of chunks with embeddings
     * @throws EmbeddingFailedException If embedding generation fails
     */
    private function generateEmbeddings(array $chunks, array $options): array
    {
        if (empty($chunks)) {
            return [];
        }

        $model = $options['embedding_model'] ?? 'models/gemini-embedding-001';

        try {
            $provider = $this->embeddingProviderFactory->create($model);

            // Extract texts from chunks
            $texts = array_map(fn($chunk) => $chunk->content, $chunks);

            // Generate embeddings in batch
            return $provider->generateEmbeddingsBatch($texts, $model);
        } catch (\Exception $e) {
            if ($e instanceof EmbeddingFailedException) {
                throw $e;
            }
            throw new EmbeddingFailedException(
                "Failed to generate embeddings: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Map embeddings to chunks and save embedding metadata to MySQL.
     *
     * Note: As per architecture best practices, we NO LONGER store embedding vectors
     * in MySQL metadata. Vectors are kept exclusively in Qdrant for optimal storage efficiency.
     * However, we DO store important metadata (dimension, model, chunk_size, overlap) for reference.
     *
     * @param array<ChunkDTO> $chunks Array of chunks
     * @param array<\App\Domains\RAG\DTOs\EmbeddingDTO> $embeddings Array of embedding DTOs
     * @return array<ChunkDTO> Array of chunks with embeddings
     * @throws EmbeddingFailedException If mapping fails
     */
    private function mapEmbeddingsToChunks(array $chunks, array $embeddings): array
    {
        $chunksWithEmbeddings = [];
        $metadataUpdates = [];

        foreach ($chunks as $index => $chunk) {
            $embeddingDTO = $embeddings[$index] ?? null;
            if ($embeddingDTO instanceof \App\Domains\RAG\DTOs\EmbeddingDTO) {
                // Prepare metadata update with embedding info (but NOT the vector itself)
                $chunkModel = $this->chunkRepository->findById($chunk->id);
                if ($chunkModel) {
                    $metadata = $chunkModel->metadata ?? [];

                    // Store embedding metadata (dimension, model) - NOT the vector
                    $metadata['embedding_dimension'] = $embeddingDTO->dimension;
                    $metadata['embedding_model'] = $embeddingDTO->model;

                    // Preserve existing chunk metadata (chunk_size, overlap, etc.)
                    // These should already be set from TextChunkingService, but ensure they're kept

                    $metadataUpdates[$chunk->id] = $metadata;
                }

                $chunksWithEmbeddings[] = $chunk->withEmbedding($embeddingDTO);
            } else {
                throw new EmbeddingFailedException(
                    "Failed to generate embedding for chunk at index {$index}"
                );
            }
        }

        // Batch update metadata for all chunks (without vectors)
        if (!empty($metadataUpdates)) {
            $this->chunkRepository->batchUpdateMetadata($metadataUpdates);
        }

        return $chunksWithEmbeddings;
    }

    /**
     * Store vectors in vector store.
     *
     * @param array<ChunkDTO> $chunks Array of chunks with embeddings
     * @return void
     * @throws \RuntimeException If storage fails
     */
    private function storeVectors(array $chunks): void
    {
        if (empty($chunks)) {
            return;
        }

        try {
            $vectorDimension = $chunks[0]->embedding?->dimension ?? 1536;
            $this->vectorStore->ensureCollection($vectorDimension);
            $this->vectorStore->upsertChunks($chunks);
        } catch (\Exception $e) {
            throw new VectorStoreException(
                "Failed to store vectors: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Update persisted chunks with vector store IDs when the driver requires it (e.g. Qdrant).
     * No-op when using drivers that store vectors in the same table (e.g. pgsql).
     *
     * @param array<ChunkDTO> $chunksWithEmbeddings Chunk DTOs with embeddings
     * @param array<string, mixed> $options Processing options
     * @return void
     */
    private function updateChunksWithVectorStoreIds(array $chunksWithEmbeddings, array $options): void
    {
        if (config('vectorstore.default') !== 'qdrant') {
            return;
        }

        if (empty($chunksWithEmbeddings)) {
            return;
        }

        $versionId = $options['version_id'] ?? null;
        if (!$versionId) {
            return;
        }

        $persistedChunks = $this->chunkRepository->findByVersionId($versionId);
        $chunkDTOsMap = [];
        foreach ($chunksWithEmbeddings as $chunkDTO) {
            $chunkDTOsMap[$chunkDTO->id] = $chunkDTO;
        }

        $vectorStoreIdUpdates = [];
        foreach ($persistedChunks as $chunk) {
            $chunkDTO = $chunkDTOsMap[$chunk->id] ?? null;
            if (!$chunkDTO || !$chunkDTO->embedding) {
                continue;
            }
            $uuid = $chunkDTO->metadata['uuid'] ?? $chunk->uuid;
            if ($uuid) {
                $vectorStoreIdUpdates[$chunk->id] = $uuid;
            }
        }

        if (!empty($vectorStoreIdUpdates)) {
            $this->chunkRepository->batchUpdateQdrantIds($vectorStoreIdUpdates, config('vectorstore.drivers.qdrant.collection_name'));
        }
    }
}
