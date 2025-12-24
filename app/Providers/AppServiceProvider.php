<?php

namespace App\Providers;

use App\Contracts\V1\SuperAdminLoginServiceInterface;
use App\Contracts\V1\SuperAdminLogOutServiceInterface;
use App\Repositories\V1\AdminActionRepositoryInterface;
use App\Repositories\V1\UserRepositoryInterface;
use App\Repositories\V1\AdminActionRepository;
use App\Repositories\V1\UserRepository;
use App\Services\V1\Auth\SuperAdminLoginService;
use App\Services\V1\Auth\SuperAdminLogOutService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind repository interfaces to implementations
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(AdminActionRepositoryInterface::class, AdminActionRepository::class);

        // Bind service interfaces to implementations
        $this->app->bind(SuperAdminLoginServiceInterface::class, SuperAdminLoginService::class);
        $this->app->bind(SuperAdminLogOutServiceInterface::class, SuperAdminLogOutService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
