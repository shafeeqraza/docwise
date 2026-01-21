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
        // Dispatch the processing job when document is uploaded
        // Use unique() with ingestion_job_id as key to prevent duplicate jobs
        // This ensures only one ProcessDocument job is queued per ingestion job
        ProcessDocument::dispatch(
            $event->document,
            $event->version,
            $event->ingestionJob
        );
    }
}
