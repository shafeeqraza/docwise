<?php

namespace App\Http\Resources;

use App\Services\V1\Company\ApiKeyUsageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiKeyResource extends JsonResource
{
    public ?string $plainKey = null;

    /**
     * Create a new resource instance.
     *
     * @param mixed $resource
     * @param string|null $plainKey
     */
    public function __construct($resource, ?string $plainKey = null)
    {
        parent::__construct($resource);
        $this->plainKey = $plainKey;
    }
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $usageService = app(ApiKeyUsageService::class);
        $usageStats = $usageService->getUsageStats($this->resource, 'this_month');
        $rateLimitCheck = $usageService->checkRateLimit($this->resource);

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'key_prefix' => $this->key_prefix,
            'masked_key' => $this->getMaskedKey(),
            'key' => $this->when(!is_null($this->plainKey), $this->plainKey),
            'permissions' => $this->permissions,
            'rate_limit_per_minute' => $this->rate_limit_per_minute,
            'rate_limit_per_hour' => $this->rate_limit_per_hour,
            'is_active' => $this->is_active,
            'is_valid' => $this->isValid(),
            'is_expired' => $this->isExpired(),
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'days_until_expiry' => $this->getDaysUntilExpiry(),
            'usage' => [
                'this_month' => $usageStats,
                'rate_limit' => $rateLimitCheck,
            ],
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'created_by' => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
                'email' => $this->createdBy->email,
            ] : null,
        ];
    }
}
