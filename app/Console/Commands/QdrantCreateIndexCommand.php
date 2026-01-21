<?php

namespace App\Console\Commands;

use App\Domains\RAG\VectorStores\Qdrant\QdrantVectorStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class QdrantCreateIndexCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'qdrant:create-index
                            {collection=documents : The collection name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create payload indexes for Qdrant collection';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $collectionName = $this->argument('collection');
        $apiKey = config('qdrant.api_key');
        $headers = $apiKey ? ['api-key' => $apiKey] : [];

        $this->info("Creating payload indexes for collection: {$collectionName}");

        // Check if collection exists
        $checkUrl = $this->buildQdrantUrl("/collections/{$collectionName}");
        $checkResponse = Http::withHeaders($headers)->get($checkUrl);

        if (!$checkResponse->successful()) {
            $this->error("Collection '{$collectionName}' does not exist.");
            $this->info("Run 'php artisan qdrant:create-collection' to create it first.");
            return self::FAILURE;
        }

        // Create indexes
        $indexes = [
            ['field' => 'company_id', 'type' => 'integer'],
            ['field' => 'document_id', 'type' => 'integer'],
            ['field' => 'version_id', 'type' => 'integer'],
            ['field' => 'chunk_id', 'type' => 'integer'],
        ];

        foreach ($indexes as $index) {
            $this->info("Creating index for {$index['field']}...");

            $indexUrl = $this->buildQdrantUrl("/collections/{$collectionName}/index");
            $indexResponse = Http::withHeaders($headers)->put($indexUrl, [
                'field_name' => $index['field'],
                'field_schema' => $index['type'],
            ]);

            if ($indexResponse->successful()) {
                $this->info("✓ Created index for {$index['field']}");
            } else {
                $this->warn("✗ Failed to create index for {$index['field']}: " . $indexResponse->body());
            }
        }

        $this->newLine();
        $this->info('Payload indexes created successfully!');

        return self::SUCCESS;
    }

    /**
     * Build Qdrant URL.
     *
     * @param string $path
     * @return string
     */
    private function buildQdrantUrl(string $path): string
    {
        $host = config('qdrant.host', 'localhost');
        $port = config('qdrant.port', 6333);

        // Remove any existing protocol from host
        $host = preg_replace('#^https?://#', '', $host);

        // Determine protocol: use https for cloud instances, http for localhost
        $protocol = ($host !== 'localhost' && $host !== '127.0.0.1') ? 'https' : 'http';

        // For HTTPS (cloud), don't include port (uses default 443)
        // For HTTP (localhost), include port
        if ($protocol === 'https') {
            return "https://{$host}{$path}";
        } else {
            return "http://{$host}:{$port}{$path}";
        }
    }
}
