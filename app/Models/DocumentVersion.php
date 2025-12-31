<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentVersion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'document_id',
        'version',
        'processing_state',
        'chunk_count',
        'token_count',
        'embedding_model',
        'chunk_strategy',
        'error_log',
        'processing_started_at',
        'processing_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'chunk_strategy' => 'array',
            'processing_started_at' => 'datetime',
            'processing_completed_at' => 'datetime',
        ];
    }

    // Relationships
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class, 'version_id');
    }

    // Helper methods
    public function isCompleted(): bool
    {
        return $this->processing_state === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->processing_state === 'failed';
    }

    public function isProcessing(): bool
    {
        return in_array($this->processing_state, ['pending', 'parsing', 'chunking', 'embedding']);
    }

    public function getProcessingDuration(): ?int
    {
        if (!$this->processing_started_at || !$this->processing_completed_at) {
            return null;
        }

        return $this->processing_started_at->diffInSeconds($this->processing_completed_at);
    }

    public function getProgressPercentage(): int
    {
        $states = ['pending', 'parsing', 'chunking', 'embedding', 'completed'];
        $currentIndex = array_search($this->processing_state, $states);

        if ($currentIndex === false) return 0;
        if ($this->processing_state === 'failed') return 0;

        return (int) (($currentIndex / (count($states) - 1)) * 100);
    }
}
