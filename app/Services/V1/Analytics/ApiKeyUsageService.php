<?php

namespace App\Services\V1\Analytics;

use App\Http\Resources\ApiKeyUsageStatsResource;
use App\Models\CompanyApiKey;
use App\Repositories\V1\Contracts\ApiKeyUsageLogRepositoryInterface;
use App\Services\V1\Contracts\ApiKeyUsageServiceInterface;

/**
 * Service for tracking and analyzing API key usage.
 *
 * Follows Single Responsibility Principle (SRP): Only usage tracking and analytics logic.
 * Follows Dependency Inversion Principle (DIP): Depends on repository interface, not implementation.
 */
class ApiKeyUsageService implements ApiKeyUsageServiceInterface
{
    public function __construct(
        private readonly ApiKeyUsageLogRepositoryInterface $usageLogRepository
    ) {}

    /**
     * Log an API request.
     *
     * @param CompanyApiKey $apiKey The API key used
     * @param array<string, mixed> $data Request data
     * @return void
     */
    #[\Override]
    public function logRequest(CompanyApiKey $apiKey, array $data): void
    {
        $this->usageLogRepository->create([
            'api_key_id' => $apiKey->id,
            'company_id' => $apiKey->company_id,
            'endpoint' => $data['endpoint'] ?? '',
            'method' => $data['method'] ?? 'GET',
            'session_id' => $data['session_id'] ?? null,
            'tokens_prompt' => $data['tokens_prompt'] ?? 0,
            'tokens_completion' => $data['tokens_completion'] ?? 0,
            'latency_ms' => $data['latency_ms'] ?? 0,
            'status_code' => $data['status_code'] ?? 200,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'created_at' => now(),
        ]);
    }

    /**
     * Get usage statistics for an API key.
     *
     * @param int $apiKeyId The API key ID
     * @param string $period Period: 'today', 'week', 'month', 'year', or 'all'
     * @return ApiKeyUsageStatsResource Usage statistics resource
     */
    #[\Override]
    public function getUsageStats(int $apiKeyId, string $period = 'month'): ApiKeyUsageStatsResource
    {
        $stats = $this->usageLogRepository->getStatsForApiKey($apiKeyId, $period);

        return new ApiKeyUsageStatsResource([
            'stats' => $stats,
            'period' => $period,
        ]);
    }

    /**
     * Get usage statistics by company.
     *
     * @param int $companyId The company ID
     * @param string $period Period: 'today', 'week', 'month', 'year', or 'all'
     * @return ApiKeyUsageStatsResource Usage statistics resource
     */
    #[\Override]
    public function getUsageByCompany(int $companyId, string $period = 'month'): ApiKeyUsageStatsResource
    {
        $stats = $this->usageLogRepository->getStatsForCompany($companyId, $period);

        return new ApiKeyUsageStatsResource([
            'stats' => $stats,
            'period' => $period,
        ]);
    }
}
