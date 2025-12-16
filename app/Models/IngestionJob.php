<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IngestionJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'company_id',
        'document_id',
        'job_type',
        'status',
        'priority',
        'attempts',
        'max_attempts',
        'payload',
        'progress_data',
        'error_message',
        'queued_at',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'progress_data' => 'array',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid();
            }
            if (empty($model->queued_at)) {
                $model->queued_at = now();
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

    // Helper methods
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isPending(): bool
    {
        return $this->status === 'queued';
    }

    public function canRetry(): bool
    {
        return $this->isFailed() && $this->attempts < $this->max_attempts;
    }

    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
            'attempts' => $this->attempts + 1,
        ]);
    }

    public function markAsCompleted(array $progressData = []): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'progress_data' => array_merge($this->progress_data ?? [], $progressData),
        ]);
    }

    public function markAsFailed(string $errorMessage, array $progressData = []): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => $errorMessage,
            'progress_data' => array_merge($this->progress_data ?? [], $progressData),
        ]);
    }

    public function getDuration(): ?int
    {
        if (!$this->started_at) return null;

        $endTime = $this->completed_at ?? $this->failed_at ?? now();
        return $this->started_at->diffInSeconds($endTime);
    }

    public function getProgressPercentage(): int
    {
        $progressData = $this->progress_data ?? [];

        if (isset($progressData['percentage'])) {
            return (int) $progressData['percentage'];
        }

        if (isset($progressData['processed'], $progressData['total']) && $progressData['total'] > 0) {
            return (int) (($progressData['processed'] / $progressData['total']) * 100);
        }

        return $this->isCompleted() ? 100 : 0;
    }
}
