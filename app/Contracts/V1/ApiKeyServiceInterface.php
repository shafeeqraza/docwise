<?php

namespace App\Contracts\V1;

use App\Models\Company;
use App\Models\CompanyApiKey;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ApiKeyServiceInterface
{
    /**
     * Get all API keys for a company.
     *
     * @param Company $company
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllForCompany(Company $company, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get API key by ID or UUID.
     *
     * @param Company $company
     * @param string|int $identifier
     * @return CompanyApiKey
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getApiKey(Company $company, string|int $identifier): CompanyApiKey;

    /**
     * Create a new API key.
     *
     * @param Company $company
     * @param User $user
     * @param array $data
     * @return array{apiKey: CompanyApiKey, plainKey: string}
     */
    public function createApiKey(Company $company, User $user, array $data): array;

    /**
     * Update API key.
     *
     * @param CompanyApiKey $apiKey
     * @param array $data
     * @return CompanyApiKey
     */
    public function updateApiKey(CompanyApiKey $apiKey, array $data): CompanyApiKey;

    /**
     * Delete (revoke) API key.
     *
     * @param CompanyApiKey $apiKey
     * @return bool
     */
    public function deleteApiKey(CompanyApiKey $apiKey): bool;

    /**
     * Regenerate API key.
     *
     * @param CompanyApiKey $apiKey
     * @return array{apiKey: CompanyApiKey, plainKey: string}
     */
    public function regenerateApiKey(CompanyApiKey $apiKey): array;

    /**
     * Validate API key.
     *
     * @param string $key
     * @return CompanyApiKey|null
     */
    public function validateApiKey(string $key): ?CompanyApiKey;
}
