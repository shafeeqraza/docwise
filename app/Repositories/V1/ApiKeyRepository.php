<?php

namespace App\Repositories\V1;

use App\Models\CompanyApiKey;
use App\Repositories\V1\Contracts\ApiKeyRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ApiKeyRepository implements ApiKeyRepositoryInterface
{
    /**
     * Get all API keys for a company with pagination.
     *
     * @param int $companyId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    #[\Override]
    public function getAllForCompany(int $companyId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = CompanyApiKey::where('company_id', $companyId);

        // Apply filters
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('key_prefix', 'like', "%{$search}%");
            });
        }

        // Order by created_at desc by default
        $query->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Find API key by ID.
     *
     * @param int $id
     * @return CompanyApiKey|null
     */
    #[\Override]
    public function findById(int $id): ?CompanyApiKey
    {
        return CompanyApiKey::find($id);
    }

    /**
     * Find API key by UUID.
     *
     * @param string $uuid
     * @return CompanyApiKey|null
     */
    #[\Override]
    public function findByUuid(string $uuid): ?CompanyApiKey
    {
        return CompanyApiKey::where('uuid', $uuid)->first();
    }

    /**
     * Find API key by key hash.
     *
     * @param string $keyHash
     * @return CompanyApiKey|null
     */
    #[\Override]
    public function findByKeyHash(string $keyHash): ?CompanyApiKey
    {
        return CompanyApiKey::where('key_hash', $keyHash)->first();
    }

    /**
     * Find API keys by key prefix.
     *
     * @param string $keyPrefix
     * @return Collection
     */
    #[\Override]
    public function findByKeyPrefix(string $keyPrefix): Collection
    {
        return CompanyApiKey::where('key_prefix', $keyPrefix)->get();
    }

    /**
     * Create a new API key.
     *
     * @param array $data
     * @return CompanyApiKey
     */
    #[\Override]
    public function create(array $data): CompanyApiKey
    {
        return CompanyApiKey::create($data);
    }

    /**
     * Update API key.
     *
     * @param CompanyApiKey $apiKey
     * @param array $data
     * @return bool
     */
    #[\Override]
    public function update(CompanyApiKey $apiKey, array $data): bool
    {
        return $apiKey->update($data);
    }

    /**
     * Delete API key.
     *
     * @param CompanyApiKey $apiKey
     * @return bool
     */
    #[\Override]
    public function delete(CompanyApiKey $apiKey): bool
    {
        return $apiKey->delete();
    }

    /**
     * Update last used timestamp.
     *
     * @param CompanyApiKey $apiKey
     * @return bool
     */
    #[\Override]
    public function updateLastUsed(CompanyApiKey $apiKey): bool
    {
        return $apiKey->update(['last_used_at' => now()]);
    }
}
