<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKeyUsageLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'api_key_id',
        'company_id',
        'endpoint',
        'method',
        'request_id',
        'session_id',
        'tokens_prompt',
        'tokens_completion',
        'latency_ms',
        'status_code',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->request_id)) {
                $model->request_id = Str::uuid();
            }
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    // Relationships
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(CompanyApiKey::class, 'api_key_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // Helper methods
    public function getTotalTokens(): int
    {
        return $this->tokens_prompt + $this->tokens_completion;
    }
}
