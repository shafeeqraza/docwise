<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetrievedChunk extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'chunk_id',
        'document_id',
        'similarity_score',
        'rank_position',
        'used_in_context',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'similarity_score' => 'decimal:4',
            'used_in_context' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    // Relationships
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(DocumentChunk::class, 'chunk_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    // Helper methods
    public function getFormattedScore(): string
    {
        return number_format($this->similarity_score * 100, 1) . '%';
    }

    public function isHighConfidence(): bool
    {
        return $this->similarity_score >= 0.8;
    }

    public function isMediumConfidence(): bool
    {
        return $this->similarity_score >= 0.6 && $this->similarity_score < 0.8;
    }

    public function isLowConfidence(): bool
    {
        return $this->similarity_score < 0.6;
    }

    public function getConfidenceLevel(): string
    {
        if ($this->isHighConfidence()) {
            return 'high';
        }
        
        if ($this->isMediumConfidence()) {
            return 'medium';
        }
        
        return 'low';
    }

    public function getDocumentTitle(): string
    {
        return $this->document->title ?? 'Unknown Document';
    }

    public function getChunkPreview(int $length = 150): string
    {
        return $this->chunk ? $this->chunk->getContentPreview($length) : '';
    }
}