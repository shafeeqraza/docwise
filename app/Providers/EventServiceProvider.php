<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * Left empty on purpose: listeners in app/Listeners are auto-discovered from
     * their handle() type hint. Run `php artisan event:list` to see the wiring.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // Model observers are registered on the models via #[ObservedBy].
    }
}
