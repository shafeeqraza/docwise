<?php

namespace App\Events;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\IngestionJob;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentUploaded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Document $document,
        public DocumentVersion $version,
        public IngestionJob $ingestionJob
    ) {}
}
