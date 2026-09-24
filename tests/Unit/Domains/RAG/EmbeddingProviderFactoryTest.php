<?php

namespace Tests\Unit\Domains\RAG;

use App\Domains\RAG\Contracts\EmbeddingProvider;
use App\Domains\RAG\Embeddings\Gemini\GeminiEmbeddingProvider;
use App\Domains\RAG\Exceptions\EmbeddingFailedException;
use App\Domains\RAG\Factories\EmbeddingProviderFactory;
use Tests\TestCase;

class EmbeddingProviderFactoryTest extends TestCase
{
    private function factory(): EmbeddingProviderFactory
    {
        return new EmbeddingProviderFactory($this->app);
    }

    public function test_creates_gemini_provider_for_gemini_model(): void
    {
        $this->assertInstanceOf(
            GeminiEmbeddingProvider::class,
            $this->factory()->create('models/gemini-embedding-001')
        );
    }

    public function test_driver_by_name_returns_the_same_instance_as_create(): void
    {
        $factory = $this->factory();

        $this->assertSame($factory->driver('gemini'), $factory->create('models/gemini-embedding-001'));
    }

    public function test_unknown_model_throws_and_lists_driver_names(): void
    {
        $this->expectException(EmbeddingFailedException::class);
        $this->expectExceptionMessage('Available providers: gemini');

        $this->factory()->create('text-embedding-3-small');
    }

    public function test_unknown_driver_name_throws(): void
    {
        $this->expectException(EmbeddingFailedException::class);

        $this->factory()->driver('nope');
    }

    public function test_registered_provider_is_checked_first(): void
    {
        $custom = $this->createMock(EmbeddingProvider::class);
        $custom->method('supports')->willReturn(true);

        $factory = $this->factory();
        $factory->register($custom);

        $this->assertSame($custom, $factory->create('models/gemini-embedding-001'));
    }

    public function test_container_binding_resolves_through_the_factory(): void
    {
        $this->assertInstanceOf(GeminiEmbeddingProvider::class, $this->app->make(EmbeddingProvider::class));
    }
}
