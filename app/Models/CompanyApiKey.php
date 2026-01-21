<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CompanyApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'company_id',
        'name',
        'key_hash',
        'key_prefix',
        'permissions',
        'allowed_domain',
        'rate_limit_per_minute',
        'rate_limit_per_hour',
        'is_active',
        'last_used_at',
        'expires_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(ApiKeyUsageLog::class, 'api_key_id');
    }

    // Helper methods
    public static function generateKey(string $prefix = 'pk'): array
    {
        $key = $prefix . '_' . Str::random(32);
        $hash = hash('sha256', $key);
        $keyPrefix = substr($key, 0, 12);

        return [
            'key' => $key,
            'hash' => $hash,
            'prefix' => $keyPrefix,
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];
        return in_array($permission, $permissions) || in_array('*', $permissions);
    }

    public function updateLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public function getMaskedKey(): string
    {
        return $this->key_prefix . '...' . str_repeat('*', 20);
    }

    public function getDaysUntilExpiry(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        return now()->diffInDays($this->expires_at, false);
    }

    public function isNearExpiry(int $days = 30): bool
    {
        $daysUntilExpiry = $this->getDaysUntilExpiry();
        return $daysUntilExpiry !== null && $daysUntilExpiry <= $days && $daysUntilExpiry >= 0;
    }

    public function getUsageToday(): int
    {
        // This would typically be tracked in a separate usage tracking system
        // For now, return 0 as placeholder
        return 0;
    }

    public function getRemainingHourlyRequests(): int
    {
        $usedThisHour = $this->getUsageToday(); // Placeholder
        return max(0, $this->rate_limit_per_hour - $usedThisHour);
    }

    public function getPermissionsString(): string
    {
        if (!$this->permissions) {
            return 'No permissions';
        }
        
        if (in_array('*', $this->permissions)) {
            return 'All permissions';
        }
        
        return implode(', ', $this->permissions);
    }

    public function isDomainAllowed(string $domain): bool
    {
        // Normalize domains for comparison (lowercase, remove protocol/port)
        $normalizedDomain = $this->normalizeDomain($domain);
        $normalizedAllowed = $this->normalizeDomain($this->allowed_domain);

        // Exact match (case-insensitive)
        return $normalizedDomain === $normalizedAllowed;
    }

    private function normalizeDomain(string $domain): string
    {
        // Remove protocol if present
        $domain = preg_replace('#^https?://#', '', $domain);

        // Remove port if present
        $domain = preg_replace('#:\d+$#', '', $domain);

        // Remove trailing slash
        $domain = rtrim($domain, '/');

        // Convert to lowercase
        return strtolower($domain);
    }

    /**
     * Get usage count for a period.
     *
     * @param string $period Period: 'today', 'week', 'month', 'year', or 'all'
     * @return int Usage count
     */
    public function getUsageCount(string $period = 'month'): int
    {
        $query = $this->usageLogs();

        match ($period) {
            'today' => $query->whereDate('created_at', now()->today()),
            'week' => $query->where('created_at', '>=', now()->subWeek()),
            'month' => $query->where('created_at', '>=', now()->subMonth()),
            'year' => $query->where('created_at', '>=', now()->subYear()),
            'all' => null, // No filter
            default => $query->where('created_at', '>=', now()->subMonth()),
        };

        return $query->count();
    }

    /**
     * Get token usage for a period.
     *
     * @param string $period Period: 'today', 'week', 'month', 'year', or 'all'
     * @return array<string, int> Token usage statistics
     */
    public function getTokenUsage(string $period = 'month'): array
    {
        $query = $this->usageLogs();

        match ($period) {
            'today' => $query->whereDate('created_at', now()->today()),
            'week' => $query->where('created_at', '>=', now()->subWeek()),
            'month' => $query->where('created_at', '>=', now()->subMonth()),
            'year' => $query->where('created_at', '>=', now()->subYear()),
            'all' => null, // No filter
            default => $query->where('created_at', '>=', now()->subMonth()),
        };

        $stats = $query->selectRaw('
            SUM(tokens_prompt) as total_prompt,
            SUM(tokens_completion) as total_completion,
            SUM(tokens_prompt + tokens_completion) as total
        ')->first();

        return [
            'tokens_prompt' => (int) ($stats->total_prompt ?? 0),
            'tokens_completion' => (int) ($stats->total_completion ?? 0),
            'tokens_total' => (int) ($stats->total ?? 0),
        ];
    }
}
