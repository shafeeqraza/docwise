<?php

namespace App\Services\V1\Company;

use App\Contracts\V1\ApiKeyServiceInterface;
use App\Models\Company;
use App\Models\CompanyApiKey;
use App\Models\User;
use App\Repositories\V1\ApiKeyRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ApiKeyService implements ApiKeyServiceInterface
{
    /**
     * Create a new service instance.
     *
     * @param ApiKeyRepositoryInterface $apiKeyRepository
     */
    public function __construct(
        private readonly ApiKeyRepositoryInterface $apiKeyRepository
    ) {}

    /**
     * Get all API keys for a company.
     *
     * @param Company $company
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllForCompany(Company $company, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->apiKeyRepository->getAllForCompany($company->id, $filters, $perPage);
    }

    /**
     * Get API key by ID or UUID.
     *
     * @param Company $company
     * @param string|int $identifier
     * @return CompanyApiKey
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getApiKey(Company $company, string|int $identifier): CompanyApiKey
    {
        if (is_numeric($identifier)) {
            $apiKey = $this->apiKeyRepository->findById((int) $identifier);
        } else {
            $apiKey = $this->apiKeyRepository->findByUuid($identifier);
        }

        if (!$apiKey || $apiKey->company_id !== $company->id) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('API key not found');
        }

        return $apiKey;
    }

    /**
     * Create a new API key.
     *
     * @param Company $company
     * @param User $user
     * @param array $data
     * @return array{apiKey: CompanyApiKey, plainKey: string}
     */
    public function createApiKey(Company $company, User $user, array $data): array
    {
        // Generate the API key
        $keyData = CompanyApiKey::generateKey();

        // Prepare API key data
        $apiKeyData = [
            'company_id' => $company->id,
            'name' => $data['name'],
            'key_hash' => $keyData['hash'],
            'key_prefix' => $keyData['prefix'],
            'permissions' => ['widget:chat'],
            'rate_limit_per_minute' => $data['rate_limit_per_minute'] ?? 60,
            'rate_limit_per_hour' => $data['rate_limit_per_hour'] ?? 1000,
            'is_active' => $data['is_active'] ?? true,
            'expires_at' => isset($data['expires_at']) ? $data['expires_at'] : null,
            'created_by' => $user->id,
        ];

        $apiKey = $this->apiKeyRepository->create($apiKeyData);

        return [
            'apiKey' => $apiKey,
            'plainKey' => $keyData['key'],
        ];
    }

    /**
     * Update API key.
     *
     * @param CompanyApiKey $apiKey
     * @param array $data
     * @return CompanyApiKey
     */
    public function updateApiKey(CompanyApiKey $apiKey, array $data): CompanyApiKey
    {
        $this->apiKeyRepository->update($apiKey, $data);
        $apiKey->refresh();

        return $apiKey;
    }

    /**
     * Delete (revoke) API key.
     *
     * @param CompanyApiKey $apiKey
     * @return bool
     */
    public function deleteApiKey(CompanyApiKey $apiKey): bool
    {
        return $this->apiKeyRepository->delete($apiKey);
    }

    /**
     * Regenerate API key.
     *
     * @param CompanyApiKey $apiKey
     * @return array{apiKey: CompanyApiKey, plainKey: string}
     */
    public function regenerateApiKey(CompanyApiKey $apiKey): array
    {
        // Extract prefix from existing key (e.g., "cs_live" from "cs_live_abc...")
        $prefixParts = explode('_', $apiKey->key_prefix);
        $prefix = count($prefixParts) >= 2
            ? $prefixParts[0] . '_' . $prefixParts[1]
            : 'cs_live';

        $keyData = CompanyApiKey::generateKey($prefix);

        // Update the API key
        $this->apiKeyRepository->update($apiKey, [
            'key_hash' => $keyData['hash'],
            'key_prefix' => $keyData['prefix'],
        ]);

        $apiKey->refresh();

        return [
            'apiKey' => $apiKey,
            'plainKey' => $keyData['key'],
        ];
    }

    /**
     * Validate API key.
     *
     * @param string $key
     * @return CompanyApiKey|null
     */
    public function validateApiKey(string $key): ?CompanyApiKey
    {
        $keyHash = hash('sha256', $key);
        $apiKey = $this->apiKeyRepository->findByKeyHash($keyHash);

        if (!$apiKey || !$apiKey->isValid()) {
            return null;
        }

        // Update last used timestamp
        $this->apiKeyRepository->updateLastUsed($apiKey);

        return $apiKey;
    }
}
