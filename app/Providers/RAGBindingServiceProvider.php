<?php

namespace App\Providers;

use App\Domains\RAG\Clients\GeminiApiClient;
use App\Domains\RAG\Contracts\EmbeddingProvider;
use App\Domains\RAG\Contracts\VectorStore;
use App\Domains\RAG\Embeddings\Gemini\GeminiEmbeddingProvider;
use App\Domains\RAG\Factories\DocumentLoaderFactory;
use App\Domains\RAG\Factories\EmbeddingProviderFactory;
use App\Domains\RAG\Factories\LLMProviderFactory;
use App\Domains\RAG\Factories\TokenizerFactory;
use App\Domains\RAG\Pipelines\ChatRAGPipeline;
use App\Domains\RAG\Pipelines\DocumentIngestionPipeline;
use App\Domains\RAG\Services\ChunkFilter;
use App\Domains\RAG\Services\TokenOptimizer;
use App\Domains\RAG\VectorStores\Qdrant\Qdrant;
use App\Domains\RAG\VectorStores\VectorStoreManager;
use Illuminate\Support\ServiceProvider;

class RAGBindingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind Gemini API client as singleton (shared across tokenizer and embedding provider)
        $this->app->singleton(GeminiApiClient::class);

        // Bind RAG domain interfaces to implementations as singletons
        $this->app->singleton(Qdrant::class);
        $this->app->singleton(EmbeddingProvider::class, GeminiEmbeddingProvider::class);

        // Vector store: resolve from manager so driver can be switched via VECTOR_STORE_DRIVER
        $this->app->singleton(VectorStoreManager::class);
        $this->app->singleton(VectorStore::class, function ($app) {
            return $app->make(VectorStoreManager::class)->driver();
        });

        // Bind RAG factories as singletons
        $this->app->singleton(DocumentLoaderFactory::class);
        $this->app->singleton(EmbeddingProviderFactory::class);
        $this->app->singleton(TokenizerFactory::class);
        $this->app->singleton(LLMProviderFactory::class);

        // Bind RAG services as singletons
        $this->app->singleton(TokenOptimizer::class);
        $this->app->singleton(ChunkFilter::class);

        // Bind RAG pipelines as singletons
        $this->app->singleton(ChatRAGPipeline::class);
        $this->app->singleton(DocumentIngestionPipeline::class);
    }
}
