<?php

namespace App\Jobs;

use App\Domains\RAG\DTOs\DocumentDTO;
use App\Domains\RAG\Exceptions\TextExtractionException;
use App\Domains\RAG\Exceptions\UnsupportedDocumentTypeException;
use App\Domains\RAG\Pipelines\DocumentIngestionPipeline;
use App\Domains\RAG\Services\DocumentProcessingStatusService;
use App\Events\DocumentProcessed;
use App\Exceptions\DocumentProcessingException;
use App\Exceptions\EmbeddingModelNotSetException;
use App\Exceptions\TokenLimitExceededException;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\IngestionJob;
use App\Services\V1\Common\LogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs a document version through the ingestion pipeline.
 *
 * Dispatched after the upload transaction commits, and unique per document
 * version so a re-dispatch cannot embed the same version twice. The queue's
 * retry_after must stay above $timeout, or a long run is picked up again
 * while still in progress.
 */
class ProcessDocument implements ShouldBeUnique, ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 300; // 5 minutes

    public $backoff = [10, 60, 180];

    public $deleteWhenMissingModels = true;

    public int $uniqueFor = 3600;

    /**
     * Failures that retrying cannot fix; the job fails on the first attempt.
     *
     * @var array<class-string<\Throwable>>
     */
    private const NON_RETRYABLE = [
        TokenLimitExceededException::class,
        DocumentProcessingException::class,
        EmbeddingModelNotSetException::class,
        TextExtractionException::class,
        UnsupportedDocumentTypeException::class,
    ];

    public function __construct(
        public Document $document,
        public DocumentVersion $version,
        public IngestionJob $ingestionJob
    ) {}

    public function uniqueId(): string
    {
        return "{$this->document->id}:{$this->version->id}";
    }

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
                'on_progress' => function (int $percentage, string $status, int $currentStep, int $totalSteps) {
                    // Update ingestion job with progress
                    $this->ingestionJob->update([
                        'progress_data' => [
                            'percentage' => $percentage,
                            'status' => $status,
                            'current_step' => $currentStep,
                            'total_steps' => $totalSteps,
                            'updated_at' => now()->toIso8601String(),
                        ],
                    ]);
                },
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
            $logService->warning('Document processing attempt failed', [
                'document_id' => $this->document->id,
                'version_id' => $this->version->id,
                'ingestion_job_id' => $this->ingestionJob->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            foreach (self::NON_RETRYABLE as $nonRetryable) {
                if ($e instanceof $nonRetryable) {
                    // Marks the job failed immediately; failed() records the outcome.
                    $this->fail($e);

                    return;
                }
            }

            // Let the queue retry with backoff; failed() runs after the last attempt.
            throw $e;
        }

        DocumentProcessed::dispatch(
            $this->document,
            $this->version,
            count($chunkDTOs),
            array_sum(array_map(fn ($chunk) => $chunk->tokens, $chunkDTOs)),
            $embeddingModel
        );
    }

    /**
     * Handle a job that failed on its final attempt or was failed as non-retryable.
     */
    public function failed(?\Throwable $exception): void
    {
        $message = $exception?->getMessage() ?? 'Unknown error';

        app(LogService::class)->error('Document processing failed', [
            'document_id' => $this->document->id,
            'version_id' => $this->version->id,
            'ingestion_job_id' => $this->ingestionJob->id,
            'error' => $message,
            'trace' => $exception?->getTraceAsString(),
        ]);

        app(DocumentProcessingStatusService::class)
            ->markAsFailed($this->document, $this->version, $this->ingestionJob, $message);
    }
}
