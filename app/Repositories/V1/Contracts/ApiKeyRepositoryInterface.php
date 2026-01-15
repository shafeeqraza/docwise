<?php

namespace App\Repositories\V1\Contracts;

use App\Models\CompanyApiKey;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ApiKeyRepositoryInterface
{
    /**
     * Get all API keys for a company with pagination.
     *
     * @param int $companyId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllForCompany(int $companyId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Find API key by ID.
     *
     * @param int $id
     * @return CompanyApiKey|null
     */
    public function findById(int $id): ?CompanyApiKey;

    /**
     * Find API key by UUID.
     *
     * @param string $uuid
     * @return CompanyApiKey|null
     */
    public function findByUuid(string $uuid): ?CompanyApiKey;

    /**
     * Find API key by key hash.
     *
     * @param string $keyHash
     * @return CompanyApiKey|null
     */
    public function findByKeyHash(string $keyHash): ?CompanyApiKey;

    /**
     * Find API key by key prefix.
     *
     * @param string $keyPrefix
     * @return Collection
     */
    public function findByKeyPrefix(string $keyPrefix): Collection;

    /**
     * Create a new API key.
     *
     * @param array $data
     * @return CompanyApiKey
     */
    public function create(array $data): CompanyApiKey;

    /**
     * Update API key.
     *
     * @param CompanyApiKey $apiKey
     * @param array $data
     * @return bool
     */
    public function update(CompanyApiKey $apiKey, array $data): bool;

    /**
     * Delete API key.
     *
     * @param CompanyApiKey $apiKey
     * @return bool
     */
    public function delete(CompanyApiKey $apiKey): bool;

    /**
     * Update last used timestamp.
     *
     * @param CompanyApiKey $apiKey
     * @return bool
     */
    public function updateLastUsed(CompanyApiKey $apiKey): bool;
}
