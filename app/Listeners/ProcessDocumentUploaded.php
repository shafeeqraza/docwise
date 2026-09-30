<?php

namespace App\Listeners;

use App\Events\DocumentUploaded;
use App\Jobs\ProcessDocument;
use Illuminate\Queue\InteractsWithQueue;

class ProcessDocumentUploaded
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(DocumentUploaded $event): void
    {
        // ProcessDocument is unique per document version (see uniqueId()) and is
        // queued only after the upload transaction commits.
        ProcessDocument::dispatch(
            $event->document,
            $event->version,
            $event->ingestionJob
        );
    }
}
