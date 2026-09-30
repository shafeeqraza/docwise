<?php

namespace Tests\Unit\Listeners;

use App\Domains\RAG\Services\UsageMetricService;
use App\Events\DocumentProcessed;
use App\Listeners\RecordDocumentUsage;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Mockery;
use Tests\TestCase;

class RecordDocumentUsageTest extends TestCase
{
    public function test_usage_is_recorded_against_the_document_company(): void
    {
        $document = (new Document)->forceFill(['id' => 11, 'company_id' => 3]);
        $version = (new DocumentVersion)->forceFill(['id' => 22, 'document_id' => 11]);

        $usageMetricService = Mockery::mock(UsageMetricService::class);
        $usageMetricService->shouldReceive('recordDocumentProcessing')
            ->once()
            ->with(3, 2, 200, 'models/gemini-embedding-001');

        $listener = new RecordDocumentUsage($usageMetricService);

        $this->assertInstanceOf(ShouldQueue::class, $listener);

        $listener->handle(new DocumentProcessed($document, $version, 2, 200, 'models/gemini-embedding-001'));
    }
}
