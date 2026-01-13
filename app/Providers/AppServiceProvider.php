<?php

namespace App\Providers;

use App\Contracts\V1\SuperAdminLoginServiceInterface;
use App\Contracts\V1\SuperAdminLogOutServiceInterface;
use App\Contracts\V1\SuperAdminCompanyServiceInterface;
use App\Contracts\V1\DocumentServiceInterface;
use App\Repositories\V1\AdminActionRepositoryInterface;
use App\Repositories\V1\UserRepositoryInterface;
use App\Repositories\V1\CompanyRepositoryInterface;
use App\Repositories\V1\AdminActionRepository;
use App\Repositories\V1\UserRepository;
use App\Repositories\V1\CompanyRepository;
use App\Services\V1\Auth\SuperAdminLoginService;
use App\Services\V1\Auth\SuperAdminLogOutService;
use App\Models\Document;
use App\Observers\DocumentObserver;
use App\Domains\RAG\Contracts\EmbeddingProvider;
use App\Domains\RAG\Contracts\VectorStore;
use App\Domains\RAG\Embeddings\Gemini\GeminiEmbeddingProvider;
use App\Domains\RAG\Factories\DocumentLoaderFactory;
use App\Domains\RAG\Factories\EmbeddingProviderFactory;
use App\Domains\RAG\Factories\TokenizerFactory;
use App\Domains\RAG\VectorStores\Qdrant\QdrantVectorStore;
use App\Repositories\V1\DocumentChunkRepository;
use App\Repositories\V1\DocumentChunkRepositoryInterface;
use App\Services\V1\Company\SuperAdminCompanyService;
use App\Services\V1\Document\DocumentService;
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
        $this->app->bind(CompanyRepositoryInterface::class, CompanyRepository::class);
        $this->app->bind(DocumentChunkRepositoryInterface::class, DocumentChunkRepository::class);

        // Bind service interfaces to implementations
        $this->app->bind(SuperAdminLoginServiceInterface::class, SuperAdminLoginService::class);
        $this->app->bind(SuperAdminLogOutServiceInterface::class, SuperAdminLogOutService::class);
        $this->app->bind(SuperAdminCompanyServiceInterface::class, SuperAdminCompanyService::class);
        $this->app->bind(DocumentServiceInterface::class, DocumentService::class);

        // Bind RAG domain interfaces to implementations
        $this->app->bind(EmbeddingProvider::class, GeminiEmbeddingProvider::class);
        $this->app->bind(VectorStore::class, QdrantVectorStore::class);


        // Bind factories as singletons
        $this->app->singleton(DocumentLoaderFactory::class);
        $this->app->singleton(EmbeddingProviderFactory::class);
        $this->app->singleton(TokenizerFactory::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register model observers
        Document::observe(DocumentObserver::class);
    }
}
