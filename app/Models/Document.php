<?php

namespace App\Models;

use App\Enums\DocumentFileType;
use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use App\Observers\DocumentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(DocumentObserver::class)]
class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'title',
        'description',
        'source_type',
        'file_type',
        'status',
        'uploaded_by',
        'public_id',
        'file_url',
        'original_filename',
        'mime_type',
        'file_size',
        'checksum',
        'language',
        'tags',
        'metadata',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'metadata' => 'array',
            'processed_at' => 'datetime',
            'source_type' => DocumentSourceType::class,
            'file_type' => DocumentFileType::class,
            'status' => DocumentStatus::class,
        ];
    }

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    public function ingestionJobs(): HasMany
    {
        return $this->hasMany(IngestionJob::class);
    }

    public function retrievedChunks(): HasMany
    {
        return $this->hasMany(RetrievedChunk::class);
    }

    // Helper methods
    public function isProcessed(): bool
    {
        return $this->status === DocumentStatus::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === DocumentStatus::FAILED;
    }

    public function isProcessing(): bool
    {
        return in_array($this->status, [DocumentStatus::PROCESSING, DocumentStatus::UPLOADED], true);
    }

    public function getLatestVersion(): ?DocumentVersion
    {
        return $this->versions()->latest('version')->first();
    }

    public function getChunkCount(): int
    {
        return $this->chunks()->count();
    }

    public function getFileSizeFormatted(): string
    {
        if (!$this->file_size)
            return 'Unknown';

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
