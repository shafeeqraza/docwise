<?php

namespace App\Listeners;

use App\Domains\RAG\Services\UsageMetricService;
use App\Events\DocumentProcessed;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Records embedding usage for a processed document.
 *
 * Queued so a metrics failure cannot fail (and retry) an ingestion that
 * has already completed.
 */
class RecordDocumentUsage implements ShouldQueue
{
    public function __construct(
        private readonly UsageMetricService $usageMetricService
    ) {}

    public function handle(DocumentProcessed $event): void
    {
        $this->usageMetricService->recordDocumentProcessing(
            $event->document->company_id,
            $event->chunkCount,
            $event->totalTokens,
            $event->embeddingModel
        );
    }
}
