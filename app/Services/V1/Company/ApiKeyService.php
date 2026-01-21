<?php

namespace App\Services\V1\Company;

use App\Services\V1\Contracts\ApiKeyServiceInterface;
use App\Http\Resources\ApiKeyResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\Models\CompanyApiKey;
use App\Repositories\V1\Contracts\ApiKeyRepositoryInterface;
use App\Services\V1\DTOs\CreateApiKeyDTO;
use App\Services\V1\DTOs\GetApiKeyDTO;
use App\Services\V1\DTOs\ListApiKeysDTO;
use App\Services\V1\DTOs\UpdateApiKeyDTO;

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
     * @param ListApiKeysDTO $dto
     * @return \App\Http\Resources\PaginatedResourceCollection
     */
    public function getAllForCompany(ListApiKeysDTO $dto): PaginatedResourceCollection
    {
        $filters = array_filter([
            'is_active' => $dto->isActive,
            'search' => $dto->search,
        ], fn($value) => $value !== null);

        $apiKeys = $this->apiKeyRepository->getAllForCompany($dto->companyId, $filters, $dto->perPage);

        return new PaginatedResourceCollection(
            ApiKeyResource::collection($apiKeys->items()),
            $apiKeys
        );
    }

    /**
     * Get API key by ID or UUID.
     *
     * @param GetApiKeyDTO $dto
     * @return ApiKeyResource
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getApiKey(GetApiKeyDTO $dto): ApiKeyResource
    {
        if (is_numeric($dto->identifier)) {
            $apiKey = $this->apiKeyRepository->findById((int) $dto->identifier);
        } else {
            $apiKey = $this->apiKeyRepository->findByUuid($dto->identifier);
        }

        if (!$apiKey || $apiKey->company_id !== $dto->companyId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('API key not found');
        }

        return new ApiKeyResource($apiKey);
    }

    /**
     * Create a new API key.
     *
     * @param CreateApiKeyDTO $dto
     * @return ApiKeyResource
     */
    public function createApiKey(CreateApiKeyDTO $dto): ApiKeyResource
    {
        // Generate the API key
        $keyData = CompanyApiKey::generateKey();

        // Prepare API key data
        $apiKeyData = [
            'company_id' => $dto->companyId,
            'name' => $dto->name,
            'key_hash' => $keyData['hash'],
            'key_prefix' => $keyData['prefix'],
            'permissions' => ['widget:chat'],
            'rate_limit_per_minute' => $dto->rateLimitPerMinute,
            'rate_limit_per_hour' => $dto->rateLimitPerHour,
            'is_active' => $dto->isActive,
            'expires_at' => $dto->expiresAt,
            'created_by' => $dto->userId,
        ];

        $apiKey = $this->apiKeyRepository->create($apiKeyData);

        return new ApiKeyResource($apiKey, $keyData['key']);
    }

    /**
     * Update API key.
     *
     * @param int $apiKeyId
     * @param UpdateApiKeyDTO $dto
     * @return ApiKeyResource
     */
    public function updateApiKey(int $apiKeyId, UpdateApiKeyDTO $dto): ApiKeyResource
    {
        $apiKey = $this->apiKeyRepository->findById($apiKeyId);
        if (!$apiKey) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('API key not found');
        }

        $updateData = array_filter([
            'name' => $dto->name,
            'rate_limit_per_minute' => $dto->rateLimitPerMinute,
            'rate_limit_per_hour' => $dto->rateLimitPerHour,
            'is_active' => $dto->isActive,
            'expires_at' => $dto->expiresAt,
        ], fn($value) => $value !== null);

        $this->apiKeyRepository->update($apiKey, $updateData);
        $apiKey->refresh();

        return new ApiKeyResource($apiKey);
    }

    /**
     * Delete (revoke) API key.
     *
     * @param int $apiKeyId
     * @return bool
     */
    public function deleteApiKey(int $apiKeyId): bool
    {
        $apiKey = $this->apiKeyRepository->findById($apiKeyId);
        if (!$apiKey) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('API key not found');
        }

        return $this->apiKeyRepository->delete($apiKey);
    }

    /**
     * Regenerate API key.
     *
     * @param int $apiKeyId
     * @return ApiKeyResource
     */
    public function regenerateApiKey(int $apiKeyId): ApiKeyResource
    {
        $apiKey = $this->apiKeyRepository->findById($apiKeyId);
        if (!$apiKey) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('API key not found');
        }

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

        return new ApiKeyResource($apiKey, $keyData['key']);
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
