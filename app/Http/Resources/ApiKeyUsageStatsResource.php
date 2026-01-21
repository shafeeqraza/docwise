<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiKeyUsageStatsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Extract stats and period from resource array
        $stats = $this->resource['stats'] ?? null;
        $period = $this->resource['period'] ?? 'month';

        // Handle null stats (no data found)
        if ($stats === null) {
            return [
                'total_requests' => 0,
                'total_tokens_prompt' => 0,
                'total_tokens_completion' => 0,
                'total_tokens' => 0,
                'avg_latency_ms' => 0.0,
                'unique_sessions' => 0,
                'unique_api_keys' => null,
                'period' => $period,
            ];
        }

        return [
            'total_requests' => (int) ($stats->total_requests ?? 0),
            'total_tokens_prompt' => (int) ($stats->total_tokens_prompt ?? 0),
            'total_tokens_completion' => (int) ($stats->total_tokens_completion ?? 0),
            'total_tokens' => (int) ($stats->total_tokens ?? 0),
            'avg_latency_ms' => (float) ($stats->avg_latency_ms ?? 0.0),
            'unique_sessions' => (int) ($stats->unique_sessions ?? 0),
            'unique_api_keys' => isset($stats->unique_api_keys) ? (int) $stats->unique_api_keys : null,
            'period' => $period,
        ];
    }
}
