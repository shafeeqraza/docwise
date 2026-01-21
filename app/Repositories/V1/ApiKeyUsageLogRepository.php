<?php

namespace App\Repositories\V1;

use App\Models\ApiKeyUsageLog;
use App\Repositories\V1\Contracts\ApiKeyUsageLogRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ApiKeyUsageLogRepository implements ApiKeyUsageLogRepositoryInterface
{
    /**
     * Create a new usage log entry.
     *
     * @param array<string, mixed> $data
     * @return ApiKeyUsageLog
     */
    public function create(array $data): ApiKeyUsageLog
    {
        return ApiKeyUsageLog::create($data);
    }

    /**
     * Get usage statistics for an API key.
     *
     * @param int $apiKeyId
     * @param string $period Period: 'today', 'week', 'month', 'year', or 'all'
     * @return object|null Statistics object with aggregated data
     */
    public function getStatsForApiKey(int $apiKeyId, string $period): ?object
    {
        $query = ApiKeyUsageLog::where('api_key_id', $apiKeyId);

        $this->applyPeriodFilter($query, $period);

        return $query->select([
            DB::raw('COUNT(*) as total_requests'),
            DB::raw('SUM(tokens_prompt) as total_tokens_prompt'),
            DB::raw('SUM(tokens_completion) as total_tokens_completion'),
            DB::raw('SUM(tokens_prompt + tokens_completion) as total_tokens'),
            DB::raw('AVG(latency_ms) as avg_latency_ms'),
            DB::raw('COUNT(DISTINCT session_id) as unique_sessions'),
        ])->first();
    }

    /**
     * Get usage statistics for a company.
     *
     * @param int $companyId
     * @param string $period Period: 'today', 'week', 'month', 'year', or 'all'
     * @return object|null Statistics object with aggregated data
     */
    public function getStatsForCompany(int $companyId, string $period): ?object
    {
        $query = ApiKeyUsageLog::where('company_id', $companyId);

        $this->applyPeriodFilter($query, $period);

        return $query->select([
            DB::raw('COUNT(*) as total_requests'),
            DB::raw('COUNT(DISTINCT api_key_id) as unique_api_keys'),
            DB::raw('SUM(tokens_prompt) as total_tokens_prompt'),
            DB::raw('SUM(tokens_completion) as total_tokens_completion'),
            DB::raw('SUM(tokens_prompt + tokens_completion) as total_tokens'),
            DB::raw('AVG(latency_ms) as avg_latency_ms'),
            DB::raw('COUNT(DISTINCT session_id) as unique_sessions'),
        ])->first();
    }

    /**
     * Get usage logs for an API key.
     *
     * @param int $apiKeyId
     * @param string $period
     * @return Collection
     */
    public function getLogsForApiKey(int $apiKeyId, string $period): Collection
    {
        $query = ApiKeyUsageLog::where('api_key_id', $apiKeyId);

        $this->applyPeriodFilter($query, $period);

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get usage logs for a company.
     *
     * @param int $companyId
     * @param string $period
     * @return Collection
     */
    public function getLogsForCompany(int $companyId, string $period): Collection
    {
        $query = ApiKeyUsageLog::where('company_id', $companyId);

        $this->applyPeriodFilter($query, $period);

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Apply period filter to query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $period
     * @return void
     */
    private function applyPeriodFilter($query, string $period): void
    {
        match ($period) {
            'today' => $query->whereDate('created_at', '=', Carbon::today()),
            'week' => $query->where('created_at', '>=', Carbon::now()->subWeek()),
            'month' => $query->where('created_at', '>=', Carbon::now()->subMonth()),
            'year' => $query->where('created_at', '>=', Carbon::now()->subYear()),
            'all' => null, // No filter
            default => $query->where('created_at', '>=', Carbon::now()->subMonth()),
        };
    }
}
