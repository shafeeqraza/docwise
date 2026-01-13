<?php

namespace App\Jobs;

use App\Exceptions\DocumentProcessingException;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentVersion;
use App\Models\IngestionJob;
use App\Domains\RAG\DTOs\DocumentDTO;
use App\Domains\RAG\Pipelines\DocumentIngestionPipeline;
use App\Domains\RAG\Services\DocumentProcessingStatusService;
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
        DocumentIngestionPipeline $pipeline,
        DocumentProcessingStatusService $statusService
    ): void {
        $document = Document::findOrFail($this->documentId);
        $version = DocumentVersion::findOrFail($this->versionId);
        $ingestionJob = IngestionJob::findOrFail($this->ingestionJobId);

        try {
            // Mark as processing
            $statusService->markAsProcessing($document, $version, $ingestionJob);

            // Convert Document to DocumentDTO
            $documentDTO = new DocumentDTO(
                id: $document->id,
                title: $document->title,
                content: $document->file_url, // File URL for loader
                fileType: $document->file_type,
                metadata: $document->metadata ?? []
            );

            // Get embedding model from company
            $company = $document->company;
            $embeddingModel = $company->getEmbeddingModel();

            // Process document through pipeline (handles: persist chunks, generate embeddings, store vectors, update chunks and version)
            $chunkDTOs = $pipeline->process($documentDTO, [
                'embedding_model' => $embeddingModel,
                'document_id' => $document->id,
                'version_id' => $version->id,
                'company_id' => $document->company_id,
                'on_complete' => function (int $chunkCount, ?string $model) use ($version, $embeddingModel) {
                    // Update version with chunk count and embedding model
                    $version->update([
                        'chunk_count' => $chunkCount,
                        'embedding_model' => $model ?? $embeddingModel,
                    ]);
                },
            ]);

            if (empty($chunkDTOs)) {
                throw new DocumentProcessingException('No chunks created from document');
            }

            // Mark as completed
            $statusService->markAsCompleted($document, $version, $ingestionJob);
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
