<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'email',
        'phone',
        'status',
        'subscription_plan',
        'billing_cycle',
        'next_billing_date',
        'payment_status',
        'allow_overages',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'allow_overages' => 'boolean',
            'next_billing_date' => 'date',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid();
            }
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    // Relationships
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }

    public function ingestionJobs(): HasMany
    {
        return $this->hasMany(IngestionJob::class);
    }

    public function usageMetrics(): HasMany
    {
        return $this->hasMany(UsageMetric::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(CompanyApiKey::class);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getMaxDocuments(): int
    {
        $plans = config('billing.plans', []);
        return $plans[$this->subscription_plan]['max_documents'] ?? 50;
    }

    public function getTokenLimit(): int
    {
        $plans = config('billing.plans', []);
        return $plans[$this->subscription_plan]['included_tokens'] ?? 50000;
    }

    public function getEmbeddingModel(): string
    {
        return $this->settings['embedding_model'] ?? 'text-embedding-3-large';
    }

    public function getChunkSize(): int
    {
        return $this->settings['chunk_size'] ?? 500;
    }
}
