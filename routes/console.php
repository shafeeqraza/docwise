<?php

use App\Domains\RAG\Factories\EmbeddingProviderFactory;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Artisan::command('ingest:document', function () {

    $provider = app(EmbeddingProviderFactory::class)->create('models/gemini-embedding-001');

    // Generate embeddings in batch
    $embeddings = $provider->generateEmbeddingsBatch(['Hello, world!'], 'models/gemini-embedding-001');

    dd($embeddings);
})->purpose('Ingest a document');
