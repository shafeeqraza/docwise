<?php

namespace Tests\Unit\Jobs;

use App\Domains\RAG\DTOs\ChunkDTO;
use App\Domains\RAG\Pipelines\DocumentIngestionPipeline;
use App\Domains\RAG\Services\DocumentProcessingStatusService;
use App\Enums\DocumentFileType;
use App\Events\DocumentProcessed;
use App\Exceptions\TokenLimitExceededException;
use App\Jobs\ProcessDocument;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\IngestionJob;
use App\Services\V1\Common\LogService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ProcessDocumentTest extends TestCase
{
    private DocumentIngestionPipeline&MockInterface $pipeline;

    private DocumentProcessingStatusService&MockInterface $statusService;

    private LogService&MockInterface $logService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pipeline = Mockery::mock(DocumentIngestionPipeline::class);
        $this->statusService = $this->mock(DocumentProcessingStatusService::class);
        $this->logService = $this->mock(LogService::class);
        $this->logService->shouldIgnoreMissing();
    }

    private function job(): ProcessDocument
    {
        $company = (new Company)->forceFill([
            'id' => 3,
            'settings' => ['embedding_model' => 'models/gemini-embedding-001'],
        ]);

        $document = (new Document)->forceFill([
            'id' => 11,
            'company_id' => 3,
            'title' => 'Handbook',
            'file_url' => 'https://example.test/handbook.pdf',
            'file_type' => DocumentFileType::PDF,
        ]);
        $document->setRelation('company', $company);

        return new ProcessDocument(
            $document,
            (new DocumentVersion)->forceFill(['id' => 22, 'document_id' => 11]),
            (new IngestionJob)->forceFill(['id' => 33, 'document_id' => 11]),
        );
    }

    private function chunk(int $index, int $tokens): ChunkDTO
    {
        return new ChunkDTO(
            content: "chunk {$index}",
            index: $index,
            tokens: $tokens,
            id: $index + 1,
            documentId: 11,
            versionId: 22,
            companyId: 3,
        );
    }

    private function handle(ProcessDocument $job): void
    {
        $job->handle($this->pipeline, $this->statusService, $this->logService);
    }

    public function test_job_is_unique_per_document_version_and_queued_after_commit(): void
    {
        $job = $this->job();

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertInstanceOf(ShouldQueueAfterCommit::class, $job);
        $this->assertSame('11:22', $job->uniqueId());
        $this->assertSame([10, 60, 180], $job->backoff);
    }

    public function test_successful_run_completes_and_dispatches_document_processed(): void
    {
        Event::fake([DocumentProcessed::class]);

        $this->statusService->shouldReceive('markAsProcessing')->once();
        $this->statusService->shouldReceive('markAsCompleted')->once();
        $this->statusService->shouldNotReceive('markAsFailed');
        $this->pipeline->shouldReceive('process')
            ->once()
            ->with(Mockery::any(), Mockery::on(fn (array $options) => $options['company_id'] === 3
                && $options['version_id'] === 22))
            ->andReturn([$this->chunk(0, 120), $this->chunk(1, 80)]);

        $this->handle($this->job());

        Event::assertDispatched(DocumentProcessed::class, fn (DocumentProcessed $event) => $event->document->id === 11
            && $event->version->id === 22
            && $event->chunkCount === 2
            && $event->totalTokens === 200
            && $event->embeddingModel === 'models/gemini-embedding-001');
    }

    public function test_non_retryable_failure_fails_the_job_without_retrying(): void
    {
        Event::fake([DocumentProcessed::class]);

        $this->statusService->shouldReceive('markAsProcessing')->once();
        $this->statusService->shouldNotReceive('markAsFailed');
        $this->pipeline->shouldReceive('process')->andThrow(new TokenLimitExceededException);

        $job = $this->job()->withFakeQueueInteractions();

        $this->handle($job);

        $job->assertFailedWith(TokenLimitExceededException::class);
        Event::assertNotDispatched(DocumentProcessed::class);
    }

    public function test_retryable_failure_is_rethrown_without_marking_failed(): void
    {
        $this->statusService->shouldReceive('markAsProcessing')->once();
        $this->statusService->shouldNotReceive('markAsFailed');
        $this->pipeline->shouldReceive('process')->andThrow(new \RuntimeException('Gemini timeout'));

        $job = $this->job()->withFakeQueueInteractions();

        try {
            $this->handle($job);
            $this->fail('Expected the exception to be rethrown for a retry.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Gemini timeout', $e->getMessage());
        }

        $job->assertNotFailed();
    }

    public function test_failed_hook_marks_the_ingestion_job_failed(): void
    {
        $job = $this->job();

        $this->statusService->shouldReceive('markAsFailed')
            ->once()
            ->with($job->document, $job->version, $job->ingestionJob, 'Gemini timeout');

        $job->failed(new \RuntimeException('Gemini timeout'));
    }
}
