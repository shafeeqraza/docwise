<?php

namespace App\Services\V1\Contracts;

use App\Http\Resources\ApiKeyResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\Models\CompanyApiKey;
use App\Services\V1\DTOs\CreateApiKeyDTO;
use App\Services\V1\DTOs\GetApiKeyDTO;
use App\Services\V1\DTOs\ListApiKeysDTO;
use App\Services\V1\DTOs\UpdateApiKeyDTO;

interface ApiKeyServiceInterface
{
    /**
     * Get all API keys for a company.
     *
     * @param ListApiKeysDTO $dto
     * @return \App\Http\Resources\PaginatedResourceCollection
     */
    public function getAllForCompany(ListApiKeysDTO $dto): PaginatedResourceCollection;

    /**
     * Get API key by ID or UUID.
     *
     * @param GetApiKeyDTO $dto
     * @return ApiKeyResource
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getApiKey(GetApiKeyDTO $dto): ApiKeyResource;

    /**
     * Create a new API key.
     *
     * @param CreateApiKeyDTO $dto
     * @return ApiKeyResource
     */
    public function createApiKey(CreateApiKeyDTO $dto): ApiKeyResource;

    /**
     * Update API key.
     *
     * @param int $apiKeyId
     * @param UpdateApiKeyDTO $dto
     * @return ApiKeyResource
     */
    public function updateApiKey(int $apiKeyId, UpdateApiKeyDTO $dto): ApiKeyResource;

    /**
     * Delete (revoke) API key.
     *
     * @param int $apiKeyId
     * @return bool
     */
    public function deleteApiKey(int $apiKeyId): bool;

    /**
     * Regenerate API key.
     *
     * @param int $apiKeyId
     * @return ApiKeyResource
     */
    public function regenerateApiKey(int $apiKeyId): ApiKeyResource;

    /**
     * Validate API key.
     *
     * @param string $key
     * @return CompanyApiKey|null
     */
    public function validateApiKey(string $key): ?CompanyApiKey;
}
