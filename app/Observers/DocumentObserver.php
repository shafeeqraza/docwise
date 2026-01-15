<?php

namespace App\Observers;

use App\Events\DocumentUploaded;
use App\Models\Document;

class DocumentObserver
{
    /**
     * Handle the Document "created" event.
     *
     * Note: DocumentUploaded event is now fired from DocumentService
     * after version and ingestion job are created, not from here.
     *
     * @param Document $document The document instance
     * @phpstan-ignore-next-line
     */
    public function created(Document $document): void
    {
        // IMPORTANT: DocumentUploaded event is fired from DocumentService
        // after version and ingestion job are created, NOT from this observer.
        // This prevents duplicate event firing.
        //
        // If you need to add logic here, make sure it doesn't fire DocumentUploaded event
        // to avoid duplicate processing.
    }
}
