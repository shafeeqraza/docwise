<?php

namespace App\Models;

use App\Enums\UsageMetricType;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'period_start',
        'period_end',
        'metric_type',
        'documents_uploaded',
        'documents_processed',
        'chunks_created',
        'embeddings_generated',
        'chat_sessions',
        'chat_messages',
        'tokens_prompt',
        'tokens_completion',
        'vector_queries',
        'api_calls',
        'storage_bytes',
        'costs',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'costs' => 'array',
            'metric_type' => UsageMetricType::class,
        ];
    }

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // Helper methods
    public function getTotalTokens(): int
    {
        return $this->tokens_prompt + $this->tokens_completion;
    }

    public function getTotalCost(): float
    {
        if (!$this->costs) {
            return 0.0;
        }
        
        return array_sum($this->costs);
    }

    public function getCostByType(string $type): float
    {
        return $this->costs[$type] ?? 0.0;
    }

    public function getFormattedStorageSize(): string
    {
        $bytes = $this->storage_bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getTokensPerMessage(): float
    {
        if ($this->chat_messages === 0) {
            return 0.0;
        }
        
        return $this->getTotalTokens() / $this->chat_messages;
    }

    public function getProcessingSuccessRate(): float
    {
        if ($this->documents_uploaded === 0) {
            return 0.0;
        }
        
        return ($this->documents_processed / $this->documents_uploaded) * 100;
    }

    public function getAverageChunksPerDocument(): float
    {
        if ($this->documents_processed === 0) {
            return 0.0;
        }
        
        return $this->chunks_created / $this->documents_processed;
    }

    public function isDaily(): bool
    {
        return $this->metric_type === UsageMetricType::DAILY;
    }

    public function isWeekly(): bool
    {
        return $this->metric_type === UsageMetricType::WEEKLY;
    }

    public function isMonthly(): bool
    {
        return $this->metric_type === UsageMetricType::MONTHLY;
    }

    public function getPeriodLabel(): string
    {
        if ($this->isDaily()) {
            return $this->period_start->format('M j, Y');
        }
        
        if ($this->isWeekly()) {
            return 'Week of ' . $this->period_start->format('M j, Y');
        }
        
        return $this->period_start->format('F Y');
    }
}