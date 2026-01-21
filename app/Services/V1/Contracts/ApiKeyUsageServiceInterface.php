<?php

namespace App\Services\V1\Contracts;

use App\Http\Resources\ApiKeyUsageStatsResource;
use App\Models\CompanyApiKey;

interface ApiKeyUsageServiceInterface
{
    /**
     * Log an API request.
     *
     * @param CompanyApiKey $apiKey The API key used
     * @param array<string, mixed> $data Request data
     * @return void
     */
    public function logRequest(CompanyApiKey $apiKey, array $data): void;

    /**
     * Get usage statistics for an API key.
     *
     * @param int $apiKeyId The API key ID
     * @param string $period Period: 'today', 'week', 'month', 'year', or 'all'
     * @return ApiKeyUsageStatsResource Usage statistics resource
     */
    public function getUsageStats(int $apiKeyId, string $period = 'month'): ApiKeyUsageStatsResource;

    /**
     * Get usage statistics by company.
     *
     * @param int $companyId The company ID
     * @param string $period Period: 'today', 'week', 'month', 'year', or 'all'
     * @return ApiKeyUsageStatsResource Usage statistics resource
     */
    public function getUsageByCompany(int $companyId, string $period = 'month'): ApiKeyUsageStatsResource;
}
