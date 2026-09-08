<?php

namespace App\Domains\RAG\Services;

use App\Enums\DocumentStatus;
use App\Enums\DocumentVersionProcessingState;
use App\Enums\IngestionJobStatus;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\IngestionJob;

/**
 * Service for managing document processing status.
 *
 * Follows Single Responsibility Principle (SRP): Only status management logic.
 */
class DocumentProcessingStatusService
{
    /**
     * Mark document processing as started.
     *
     * @param Document $document The document
     * @param DocumentVersion $version The document version
     * @param IngestionJob $ingestionJob The ingestion job
     * @return void
     */
    public function markAsProcessing(Document $document, DocumentVersion $version, IngestionJob $ingestionJob): void
    {
        $document->update(['status' => DocumentStatus::PROCESSING]);
        $version->update(['processing_state' => DocumentVersionProcessingState::PARSING]);
        $ingestionJob->update([
            'status' => IngestionJobStatus::PROCESSING,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark document processing as completed.
     *
     * @param Document $document The document
     * @param DocumentVersion $version The document version
     * @param IngestionJob $ingestionJob The ingestion job
     * @return void
     */
    public function markAsCompleted(Document $document, DocumentVersion $version, IngestionJob $ingestionJob): void
    {
        $document->update([
            'status' => DocumentStatus::COMPLETED,
            'processed_at' => now(),
        ]);
        $version->update(['processing_state' => DocumentVersionProcessingState::COMPLETED]);
        $ingestionJob->update([
            'status' => IngestionJobStatus::COMPLETED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark document processing as failed.
     *
     * @param Document $document The document
     * @param DocumentVersion $version The document version
     * @param IngestionJob $ingestionJob The ingestion job
     * @param string $errorMessage The error message
     * @return void
     */
    public function markAsFailed(Document $document, DocumentVersion $version, IngestionJob $ingestionJob, string $errorMessage): void
    {
        $document->update(['status' => DocumentStatus::FAILED]);
        $version->update([
            'processing_state' => DocumentVersionProcessingState::FAILED,
            'error_log' => $errorMessage,
        ]);
        $ingestionJob->update([
            'status' => IngestionJobStatus::FAILED,
            'error_message' => $errorMessage,
            'failed_at' => now(),
        ]);
    }
}
