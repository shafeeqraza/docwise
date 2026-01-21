<?php

namespace App\Providers;

use App\Services\V1\Auth\SuperAdminLoginService;
use App\Services\V1\Auth\SuperAdminLogOutService;
use App\Services\V1\Company\ApiKeyService;
use App\Services\V1\Company\SuperAdminCompanyService;
use App\Services\V1\Contracts\ApiKeyServiceInterface;
use App\Services\V1\Contracts\DocumentServiceInterface;
use App\Services\V1\Contracts\SuperAdminCompanyServiceInterface;
use App\Services\V1\Contracts\SuperAdminLoginServiceInterface;
use App\Services\V1\Contracts\SuperAdminLogOutServiceInterface;
use App\Services\V1\Document\DocumentService;
use Illuminate\Support\ServiceProvider;

class ServiceBindingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind service interfaces to implementations
        $this->app->bind(SuperAdminLoginServiceInterface::class, SuperAdminLoginService::class);
        $this->app->bind(SuperAdminLogOutServiceInterface::class, SuperAdminLogOutService::class);
        $this->app->bind(SuperAdminCompanyServiceInterface::class, SuperAdminCompanyService::class);
        $this->app->bind(DocumentServiceInterface::class, DocumentService::class);
        $this->app->bind(ApiKeyServiceInterface::class, ApiKeyService::class);
    }
}
