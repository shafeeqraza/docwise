<?php

namespace App\Events;

use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by ProcessDocument once a document version has been fully ingested.
 *
 * Carries totals rather than chunk DTOs so queued listeners serialize cheaply.
 */
class DocumentProcessed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Document $document,
        public DocumentVersion $version,
        public int $chunkCount,
        public int $totalTokens,
        public string $embeddingModel
    ) {}
}
