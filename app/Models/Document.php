<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

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
        'storage_path',
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
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid();
            }
        });
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
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isProcessing(): bool
    {
        return in_array($this->status, ['processing', 'uploaded']);
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
        if (!$this->file_size) return 'Unknown';

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
