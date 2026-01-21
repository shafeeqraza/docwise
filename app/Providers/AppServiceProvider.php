<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Service bindings are handled by ServiceBindingServiceProvider
        // Repository bindings are handled by RepositoryBindingServiceProvider
        // RAG bindings are handled by RAGBindingServiceProvider
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register model observers
    }
}
