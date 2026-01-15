<?php

namespace App\Domains\RAG\Pipelines;

use App\Domains\RAG\Contracts\VectorStore;
use App\Domains\RAG\DTOs\ChunkDTO;
use App\Domains\RAG\DTOs\DocumentDTO;
use App\Domains\RAG\Exceptions\EmbeddingFailedException;
use App\Domains\RAG\Exceptions\QdrantException;
use App\Domains\RAG\Exceptions\TextExtractionException;
use App\Domains\RAG\Factories\DocumentLoaderFactory;
use App\Domains\RAG\Factories\EmbeddingProviderFactory;
use App\Domains\RAG\Services\TextChunkingService;
use App\Domains\RAG\Services\UsageMetricService;
use App\Domains\RAG\Validators\TokenLimitValidator;
use App\Repositories\V1\DocumentChunkRepositoryInterface;

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
    public function __construct(
        private DocumentLoaderFactory $loaderFactory,
        private TextChunkingService $chunkingService,
        private EmbeddingProviderFactory $embeddingProviderFactory,
        private VectorStore $vectorStore,
        private DocumentChunkRepositoryInterface $chunkRepository,
        private TokenLimitValidator $tokenLimitValidator,
        private UsageMetricService $usageMetricService
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
        // Step 1: Load document text
        $text = $this->loadDocument($document);

        // Step 2: Chunk text using TextChunkingService (returns ChunkDTOs without IDs)
        $chunkDTOs = $this->chunkingService->chunkText($text, $document, $options);

        // Step 3: Persist chunks to MySQL and update DTOs with IDs and UUIDs
        $chunkDTOs = $this->persistChunksAndUpdateDTOs($chunkDTOs, $options);

        // Step 4: Validate token limit before generating embeddings
        $companyId = $options['company_id'] ?? null;
        if ($companyId) {
            $this->tokenLimitValidator->validate($companyId, $chunkDTOs);
        }

        // Step 5: Generate embeddings for chunks
        $embeddings = $this->generateEmbeddings($chunkDTOs, $options);

        $chunksWithEmbeddings = $this->mapEmbeddingsToChunks($chunkDTOs, $embeddings);

        // Step 6: Validate token limit before storing vectors
        $companyId = $options['company_id'] ?? null;
        if ($companyId) {
            $this->tokenLimitValidator->validate(companyId: $companyId, chunks: $chunksWithEmbeddings);
        }

        // Step 7: Store vectors in vector store
        $this->storeVectors($chunksWithEmbeddings);

        // Step 8: Update persisted chunks with qdrant_point_id after vector storage
        $this->updateChunksWithQdrantIds($chunksWithEmbeddings, $options);

        // Step 9: Update usage metrics
        $companyId = $options['company_id'] ?? null;
        if ($companyId) {
            $this->usageMetricService->recordDocumentProcessing($companyId, $chunksWithEmbeddings);
        }

        // Step 10: Call completion callback if provided (for updating version, etc.)
        if (isset($options['on_complete']) && is_callable($options['on_complete'])) {
            $options['on_complete'](count($chunksWithEmbeddings), $options['embedding_model'] ?? null);
        }

        return $chunksWithEmbeddings;
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
     * Map embeddings to chunks and save vectors to chunk metadata.
     *
     * @param array<ChunkDTO> $chunks Array of chunks
     * @param array<\App\Domains\RAG\DTOs\EmbeddingDTO> $embeddings Array of embedding DTOs
     * @return array<ChunkDTO> Array of chunks with embeddings
     * @throws EmbeddingFailedException If mapping fails
     */
    private function mapEmbeddingsToChunks(array $chunks, array $embeddings): array
    {
        $chunksWithEmbeddings = [];
        foreach ($chunks as $index => $chunk) {
            $embeddingDTO = $embeddings[$index] ?? null;
            if ($embeddingDTO instanceof \App\Domains\RAG\DTOs\EmbeddingDTO) {
                // Save embedding vector to chunk metadata
                $this->saveEmbeddingToChunkMetadata($chunk->id, $embeddingDTO);

                $chunksWithEmbeddings[] = $chunk->withEmbedding($embeddingDTO);
            } else {
                throw new EmbeddingFailedException(
                    "Failed to generate embedding for chunk at index {$index}"
                );
            }
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
            // Determine vector dimension from first chunk
            $vectorDimension = $chunks[0]->embedding?->dimension ?? 1536;

            // Ensure collection exists
            $this->vectorStore->ensureCollection($vectorDimension);

            // Upsert chunks
            $this->vectorStore->upsertChunks($chunks);
        } catch (\Exception $e) {
            throw new QdrantException(
                "Failed to store vectors: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Save embedding vector to chunk metadata.
     *
     * @param int $chunkId The chunk ID
     * @param \App\Domains\RAG\DTOs\EmbeddingDTO $embeddingDTO The embedding DTO
     * @return void
     */
    private function saveEmbeddingToChunkMetadata(int $chunkId, \App\Domains\RAG\DTOs\EmbeddingDTO $embeddingDTO): void
    {
        $chunk = $this->chunkRepository->findById($chunkId);
        if (!$chunk) {
            return;
        }

        // Update metadata with embedding vector and dimension
        $metadata = $chunk->metadata ?? [];
        $metadata['embedding_vector'] = $embeddingDTO->vector;
        $metadata['embedding_dimension'] = $embeddingDTO->dimension;

        // Update embedding model if not set
        if (!$chunk->embedding_model) {
            $chunk->embedding_model = $embeddingDTO->model;
        }

        $chunk->metadata = $metadata;
        $chunk->save();
    }

    /**
     * Update persisted chunks with Qdrant point IDs.
     *
     * @param array<ChunkDTO> $chunksWithEmbeddings Chunk DTOs with embeddings
     * @param array<string, mixed> $options Processing options
     * @return void
     */
    private function updateChunksWithQdrantIds(array $chunksWithEmbeddings, array $options): void
    {
        if (empty($chunksWithEmbeddings)) {
            return;
        }

        // Load persisted chunks by version ID
        $versionId = $options['version_id'] ?? null;
        if (!$versionId) {
            return;
        }

        $persistedChunks = $this->chunkRepository->findByVersionId($versionId);

        // Create a map of chunk ID to ChunkDTO for quick lookup
        $chunkDTOsMap = [];
        foreach ($chunksWithEmbeddings as $chunkDTO) {
            $chunkDTOsMap[$chunkDTO->id] = $chunkDTO;
        }

        // Update persisted chunks with qdrant_point_id
        foreach ($persistedChunks as $chunk) {
            $chunkDTO = $chunkDTOsMap[$chunk->id] ?? null;

            if (!$chunkDTO || !$chunkDTO->embedding) {
                continue; // Skip chunks without embeddings
            }

            // Use UUID from metadata as qdrant_point_id
            $uuid = $chunkDTO->metadata['uuid'] ?? $chunk->uuid;

            if ($uuid) {
                $chunk->qdrant_point_id = $uuid;
                $chunk->qdrant_collection = 'documents';
                $chunk->save();
            }
        }
    }
}
