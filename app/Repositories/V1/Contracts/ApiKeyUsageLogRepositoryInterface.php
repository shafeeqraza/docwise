<?php

namespace App\Repositories\V1\Contracts;

use App\Models\ApiKeyUsageLog;
use Illuminate\Database\Eloquent\Collection;

interface ApiKeyUsageLogRepositoryInterface
{
    /**
     * Create a new usage log entry.
     *
     * @param array<string, mixed> $data
     * @return ApiKeyUsageLog
     */
    public function create(array $data): ApiKeyUsageLog;

    /**
     * Get usage statistics for an API key.
     *
     * @param int $apiKeyId
     * @param string $period Period: 'today', 'week', 'month', 'year', or 'all'
     * @return object|null Statistics object with aggregated data
     */
    public function getStatsForApiKey(int $apiKeyId, string $period): ?object;

    /**
     * Get usage statistics for a company.
     *
     * @param int $companyId
     * @param string $period Period: 'today', 'week', 'month', 'year', or 'all'
     * @return object|null Statistics object with aggregated data
     */
    public function getStatsForCompany(int $companyId, string $period): ?object;

    /**
     * Get usage logs for an API key.
     *
     * @param int $apiKeyId
     * @param string $period
     * @return Collection
     */
    public function getLogsForApiKey(int $apiKeyId, string $period): Collection;

    /**
     * Get usage logs for a company.
     *
     * @param int $companyId
     * @param string $period
     * @return Collection
     */
    public function getLogsForCompany(int $companyId, string $period): Collection;
}
