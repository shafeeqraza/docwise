<?php

namespace App\Observers;

use App\Models\Document;
use Illuminate\Support\Str;

/**
 * Registered on Document via #[ObservedBy].
 *
 * IMPORTANT: DocumentUploaded is fired from DocumentService after the version and
 * ingestion job are created, NOT from this observer. Do not fire it from a
 * created() hook here, or documents will be processed twice.
 */
class DocumentObserver
{
    /**
     * Handle the Document "creating" event.
     *
     * @param Document $document The document instance
     */
    public function creating(Document $document): void
    {
        if (empty($document->uuid)) {
            $document->uuid = Str::uuid();
        }
    }
}
