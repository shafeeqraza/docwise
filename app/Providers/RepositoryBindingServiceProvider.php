<?php

namespace App\Providers;

use App\Repositories\V1\AdminActionRepository;
use App\Repositories\V1\ApiKeyRepository;
use App\Repositories\V1\CompanyRepository;
use App\Repositories\V1\Contracts\AdminActionRepositoryInterface;
use App\Repositories\V1\Contracts\ApiKeyRepositoryInterface;
use App\Repositories\V1\Contracts\CompanyRepositoryInterface;
use App\Repositories\V1\Contracts\DocumentChunkRepositoryInterface;
use App\Repositories\V1\Contracts\UserRepositoryInterface;
use App\Repositories\V1\DocumentChunkRepository;
use App\Repositories\V1\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryBindingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind repository interfaces to implementations
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(AdminActionRepositoryInterface::class, AdminActionRepository::class);
        $this->app->bind(CompanyRepositoryInterface::class, CompanyRepository::class);
        $this->app->bind(DocumentChunkRepositoryInterface::class, DocumentChunkRepository::class);
        $this->app->bind(ApiKeyRepositoryInterface::class, ApiKeyRepository::class);
    }
}
