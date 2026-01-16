<?php

namespace App\Providers;

use App\Events\DocumentUploaded;
use App\Listeners\ProcessDocumentUploaded;
use App\Models\Document as ModelsDocument;
use App\Observers\DocumentObserver;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        DocumentUploaded::class => [
            ProcessDocumentUploaded::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        ModelsDocument::observe(DocumentObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
