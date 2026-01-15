<?php

namespace App\Domains\RAG\Services;

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
        $document->update(['status' => 'processing']);
        $version->update(['processing_state' => 'parsing']); // Use 'parsing' as first processing state
        $ingestionJob->update([
            'status' => 'processing',
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
            'status' => 'completed',
            'processed_at' => now(),
        ]);
        $version->update(['processing_state' => 'completed']);
        $ingestionJob->update([
            'status' => 'completed',
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
        $document->update(['status' => 'failed']);
        $version->update([
            'processing_state' => 'failed',
            'error_log' => $errorMessage,
        ]);
        $ingestionJob->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'failed_at' => now(),
        ]);
    }
}
