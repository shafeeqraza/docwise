<?php

namespace App\Listeners;

use App\Events\DocumentUploaded;
use App\Jobs\ProcessDocument;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ProcessDocumentUploaded implements ShouldQueue
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
            $event->document->id,
            $event->version->id,
            $event->ingestionJob->id
        )->unique("process_document_{$event->ingestionJob->id}");
    }
}
