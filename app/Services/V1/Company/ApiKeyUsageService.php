<?php

namespace App\Services\V1\Company;

use App\Models\CompanyApiKey;
use App\Models\UsageMetric;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ApiKeyUsageService
{
    /**
     * Track API key usage.
     *
     * @param CompanyApiKey $apiKey
     * @param array $metadata Additional metadata (tokens, etc.)
     * @return void
     */
    public function trackUsage(CompanyApiKey $apiKey, array $metadata = []): void
    {
        $companyId = $apiKey->company_id;

        // Track daily metrics
        $this->incrementDailyMetrics($companyId, [
            'api_calls' => 1,
            'tokens_prompt' => $metadata['tokens_prompt'] ?? 0,
            'tokens_completion' => $metadata['tokens_completion'] ?? 0,
        ]);

        // Track monthly metrics
        $this->incrementMonthlyMetrics($companyId, [
            'api_calls' => 1,
            'tokens_prompt' => $metadata['tokens_prompt'] ?? 0,
            'tokens_completion' => $metadata['tokens_completion'] ?? 0,
        ]);

        // Track per-endpoint usage in cache (for rate limiting)
        $this->trackEndpointUsage($apiKey);
    }

    /**
     * Get API key usage statistics.
     *
     * @param CompanyApiKey $apiKey
     * @param string $period 'today', 'this_month', 'all_time'
     * @return array
     */
    public function getUsageStats(CompanyApiKey $apiKey, string $period = 'this_month'): array
    {
        $companyId = $apiKey->company_id;
        $query = UsageMetric::where('company_id', $companyId);

        if ($period === 'today') {
            $query->where('period_start', now()->startOfDay())
                ->where('metric_type', 'daily');
        } elseif ($period === 'all_time') {
            // Get all monthly metrics
            $query->where('metric_type', 'monthly');
        } else {
            // Default to this month
            $query->where('period_start', now()->startOfMonth())
                ->where('metric_type', 'monthly');
        }

        $metrics = $query->get();

        return [
            'api_calls' => $metrics->sum('api_calls'),
            'tokens_prompt' => $metrics->sum('tokens_prompt'),
            'tokens_completion' => $metrics->sum('tokens_completion'),
            'total_tokens' => $metrics->sum(fn($m) => $m->tokens_prompt + $m->tokens_completion),
            'period' => $period,
        ];
    }

    /**
     * Check if API key has exceeded rate limits.
     *
     * @param CompanyApiKey $apiKey
     * @return array{allowed: bool, remaining_minute: int, remaining_hour: int}
     */
    public function checkRateLimit(CompanyApiKey $apiKey): array
    {
        $minuteKey = "api_key:{$apiKey->id}:minute:" . now()->format('Y-m-d-H-i');
        $hourKey = "api_key:{$apiKey->id}:hour:" . now()->format('Y-m-d-H');

        $minuteCount = Cache::get($minuteKey, 0);
        $hourCount = Cache::get($hourKey, 0);

        $remainingMinute = max(0, $apiKey->rate_limit_per_minute - $minuteCount);
        $remainingHour = max(0, $apiKey->rate_limit_per_hour - $hourCount);

        return [
            'allowed' => $minuteCount < $apiKey->rate_limit_per_minute && $hourCount < $apiKey->rate_limit_per_hour,
            'remaining_minute' => $remainingMinute,
            'remaining_hour' => $remainingHour,
            'used_minute' => $minuteCount,
            'used_hour' => $hourCount,
        ];
    }

    /**
     * Increment daily metrics.
     *
     * @param int $companyId
     * @param array $increments
     * @return void
     */
    private function incrementDailyMetrics(int $companyId, array $increments): void
    {
        $periodStart = now()->startOfDay();
        $periodEnd = now()->endOfDay();

        UsageMetric::updateOrCreate(
            [
                'company_id' => $companyId,
                'period_start' => $periodStart,
                'metric_type' => 'daily',
            ],
            [
                'period_end' => $periodEnd,
            ]
        );

        foreach ($increments as $field => $value) {
            if ($value > 0) {
                UsageMetric::where('company_id', $companyId)
                    ->where('period_start', $periodStart)
                    ->where('metric_type', 'daily')
                    ->increment($field, $value);
            }
        }
    }

    /**
     * Increment monthly metrics.
     *
     * @param int $companyId
     * @param array $increments
     * @return void
     */
    private function incrementMonthlyMetrics(int $companyId, array $increments): void
    {
        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();

        UsageMetric::updateOrCreate(
            [
                'company_id' => $companyId,
                'period_start' => $periodStart,
                'metric_type' => 'monthly',
            ],
            [
                'period_end' => $periodEnd,
            ]
        );

        foreach ($increments as $field => $value) {
            if ($value > 0) {
                UsageMetric::where('company_id', $companyId)
                    ->where('period_start', $periodStart)
                    ->where('metric_type', 'monthly')
                    ->increment($field, $value);
            }
        }
    }

    /**
     * Track endpoint usage for rate limiting.
     *
     * @param CompanyApiKey $apiKey
     * @return void
     */
    private function trackEndpointUsage(CompanyApiKey $apiKey): void
    {
        $minuteKey = "api_key:{$apiKey->id}:minute:" . now()->format('Y-m-d-H-i');
        $hourKey = "api_key:{$apiKey->id}:hour:" . now()->format('Y-m-d-H');

        Cache::increment($minuteKey);
        Cache::increment($hourKey);

        // Set expiration
        Cache::put($minuteKey, Cache::get($minuteKey), now()->addMinute());
        Cache::put($hourKey, Cache::get($hourKey), now()->addHour());
    }
}
