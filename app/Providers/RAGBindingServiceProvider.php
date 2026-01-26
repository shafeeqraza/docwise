<?php

namespace App\Providers;

use App\Domains\RAG\Contracts\EmbeddingProvider;
use App\Domains\RAG\Contracts\VectorStore;
use App\Domains\RAG\Embeddings\Gemini\Gemini;
use App\Domains\RAG\Embeddings\Gemini\GeminiEmbeddingProvider;
use App\Domains\RAG\Factories\DocumentLoaderFactory;
use App\Domains\RAG\Factories\EmbeddingProviderFactory;
use App\Domains\RAG\Factories\TokenizerFactory;
use App\Domains\RAG\Pipelines\ChatRAGPipeline;
use App\Domains\RAG\VectorStores\Qdrant\Qdrant;
use App\Domains\RAG\VectorStores\Qdrant\QdrantVectorStore;
use Illuminate\Support\ServiceProvider;

class RAGBindingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind RAG domain interfaces to implementations as singletons (stateless services)
        $this->app->singleton(Gemini::class);
        $this->app->singleton(Qdrant::class);
        $this->app->singleton(EmbeddingProvider::class, GeminiEmbeddingProvider::class);
        $this->app->singleton(VectorStore::class, QdrantVectorStore::class);

        // Bind RAG factories as singletons
        $this->app->singleton(DocumentLoaderFactory::class);
        $this->app->singleton(EmbeddingProviderFactory::class);
        $this->app->singleton(TokenizerFactory::class);
        $this->app->singleton(\App\Domains\RAG\Factories\LLMProviderFactory::class);

        // Bind RAG pipelines as singletons
        $this->app->singleton(ChatRAGPipeline::class);
    }
}
