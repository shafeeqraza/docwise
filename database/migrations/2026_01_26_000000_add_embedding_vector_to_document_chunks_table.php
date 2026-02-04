<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds pgvector embedding column for the pgsql vector store driver.
     */
    public function up(): void
    {
        $dbDriver = DB::connection()->getDriverName();
        $vectorDb = config('vectorstore.default', 'qdrant');

        if ($vectorDb !== 'pgsql' && $dbDriver !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        DB::statement('ALTER TABLE document_chunks ADD COLUMN IF NOT EXISTS embedding vector(1536)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $dbDriver = DB::connection()->getDriverName();
        $vectorDb = config('vectorstore.default', 'qdrant');

        if ($vectorDb !== 'pgsql' && $dbDriver !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE document_chunks DROP COLUMN IF EXISTS embedding');
    }
};
