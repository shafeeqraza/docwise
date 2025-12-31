<?php

namespace App\Jobs;

use App\Exceptions\DocumentProcessingException;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\IngestionJob;
use App\Services\V1\Document\Processing\DocumentProcessingStatusService;
use App\Services\V1\Document\Processing\DocumentTextExtractionService;
use App\Services\V1\Document\Processing\EmbeddingService;
use App\Services\V1\Document\Processing\TextChunkingService;
use App\Services\V1\Document\Processing\VectorStoreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300; // 5 minutes

    public function __construct(
        public int $documentId,
        public int $versionId,
        public int $ingestionJobId
    ) {}

    public function handle(
        DocumentTextExtractionService $textExtractionService,
        DocumentProcessingStatusService $statusService,
        TextChunkingService $chunkingService,
        EmbeddingService $embeddingService,
        VectorStoreService $vectorStoreService
    ): void {
        $document = Document::findOrFail($this->documentId);
        $version = DocumentVersion::findOrFail($this->versionId);
        $ingestionJob = IngestionJob::findOrFail($this->ingestionJobId);

        try {
            // Mark as processing
            $statusService->markAsProcessing($document, $version, $ingestionJob);

            // Step 1: Extract text from document
            $text = $textExtractionService->extractText($document);

            if (empty(trim($text))) {
                throw new DocumentProcessingException('No text extracted from document');
            }

            // Step 2: Chunk the text
            $chunks = $chunkingService->chunkText($text, $document, $version);

            if (empty($chunks)) {
                throw new DocumentProcessingException('No chunks created from document text');
            }

            // Step 3: Generate embeddings for chunks
            $company = $document->company;
            $embeddingModel = $company->getEmbeddingModel();
            $chunksWithEmbeddings = $embeddingService->generateEmbeddingsBatch($chunks, $embeddingModel);

            // Step 4: Store vectors in Qdrant
            $vectorStoreService->upsertChunks($chunksWithEmbeddings);

            // Update version with chunk count and embedding model
            $version->update([
                'chunk_count' => count($chunks),
                'embedding_model' => $embeddingModel,
            ]);

            // Mark as completed
            $statusService->markAsCompleted($document, $version, $ingestionJob);

            Log::info('Document processed successfully', [
                'document_id' => $document->id,
                'version_id' => $version->id,
                'ingestion_job_id' => $ingestionJob->id,
                'chunks_created' => count($chunks),
            ]);
        } catch (\Exception $e) {
            $this->handleFailure($document, $version, $ingestionJob, $e, $statusService);
            throw $e;
        }
    }

    /**
     * Handle processing failure.
     */
    private function handleFailure(
        Document $document,
        DocumentVersion $version,
        IngestionJob $ingestionJob,
        \Exception $exception,
        DocumentProcessingStatusService $statusService
    ): void {
        Log::error('Document processing failed', [
            'document_id' => $document->id,
            'version_id' => $version->id,
            'ingestion_job_id' => $ingestionJob->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $statusService->markAsFailed($document, $version, $ingestionJob, $exception->getMessage());
    }
}
