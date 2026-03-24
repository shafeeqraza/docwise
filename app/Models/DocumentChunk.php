<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DocumentChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'company_id',
        'document_id',
        'version_id',
        'chunk_index',
        'content',
        'content_hash',
        'token_count',
        'embedding_model',
        'embedding',
        'qdrant_point_id',
        'qdrant_collection',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid();
            }
            if (empty($model->content_hash)) {
                $model->content_hash = hash('sha256', $model->content);
            }
        });
    }

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'version_id');
    }

    public function retrievedChunks(): HasMany
    {
        return $this->hasMany(RetrievedChunk::class, 'chunk_id');
    }

    // Helper methods
    public function hasEmbedding(): bool
    {
        return !empty($this->qdrant_point_id);
    }

    public function getContentPreview(int $length = 200): string
    {
        return Str::limit($this->content, $length);
    }

    public function getQdrantCollectionName(): string
    {
        return $this->qdrant_collection ?: "company_{$this->company_id}_chunks";
    }

    public function getMetadataValue(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    public function setMetadataValue(string $key, $value): void
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;
    }
}
