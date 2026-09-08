<?php

use App\Enums\DocumentFileType;
use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->enum('source_type', DocumentSourceType::values())->default(DocumentSourceType::UPLOAD);
            $table->enum('file_type', DocumentFileType::values());
            $table->enum('status', DocumentStatus::values())->default(DocumentStatus::UPLOADED);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('public_id', 1000);
            $table->string('file_url', 2000)->nullable();
            $table->string('original_filename', 500)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->string('language', 10)->default('en');
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id']);
            $table->index(['status']);
            $table->index(['file_type']);
            $table->index(['created_at']);
            $table->index(['checksum']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
