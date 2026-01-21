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
use App\Services\V1\Common\LogService;

class ProcessDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300; // 5 minutes

    public function __construct(
        public Document $document,
        public DocumentVersion $version,
        public IngestionJob $ingestionJob
    ) {}

    public function handle(
        DocumentIngestionPipeline $pipeline,
        DocumentProcessingStatusService $statusService,
        LogService $logService
    ): void {
        try {
            // Mark as processing
            $statusService->markAsProcessing($this->document, $this->version, $this->ingestionJob);

            // Convert Document to DocumentDTO
            $documentDTO = new DocumentDTO(
                id: $this->document->id,
                title: $this->document->title,
                content: $this->document->file_url, // File URL for loader
                fileType: $this->document->file_type,
                metadata: $this->document->metadata ?? []
            );

            // Get embedding model from company
            $company = $this->document->company;
            $embeddingModel = $company->getEmbeddingModel();

            // Process document through pipeline (handles: persist chunks, generate embeddings, store vectors, update chunks and version)
            $chunkDTOs = $pipeline->process($documentDTO, [
                'embedding_model' => $embeddingModel,
                'document_id' => $this->document->id,
                'version_id' => $this->version->id,
                'company_id' => $this->document->company_id,
                'on_complete' => function (int $chunkCount, ?string $model) use ($embeddingModel) {
                    // Update version with chunk count and embedding model
                    $this->version->update([
                        'chunk_count' => $chunkCount,
                        'embedding_model' => $model ?? $embeddingModel,
                    ]);
                },
            ]);

            if (empty($chunkDTOs)) {
                throw new DocumentProcessingException('No chunks created from document');
            }

            // Mark as completed
            $statusService->markAsCompleted($this->document, $this->version, $this->ingestionJob);
        } catch (\Exception $e) {
            $this->handleFailure($this->document, $this->version, $this->ingestionJob, $e, $statusService, $logService);
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
        DocumentProcessingStatusService $statusService,
        LogService $logService
    ): void {
        $logService->error('Document processing failed', [
            'document_id' => $document->id,
            'version_id' => $version->id,
            'ingestion_job_id' => $ingestionJob->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $statusService->markAsFailed($document, $version, $ingestionJob, $exception->getMessage());
    }
}
