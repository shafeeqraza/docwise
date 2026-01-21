<?php

namespace App\Repositories\V1\Contracts;

use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface CompanyRepositoryInterface
{
    /**
     * Get all companies with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Find company by ID.
     *
     * @param int $id
     * @return Company|null
     */
    public function findById(int $id): ?Company;

    /**
     * Find company by UUID.
     *
     * @param string $uuid
     * @return Company|null
     */
    public function findByUuid(string $uuid): ?Company;

    /**
     * Find company by slug.
     *
     * @param string $slug
     * @return Company|null
     */
    public function findBySlug(string $slug): ?Company;

    /**
     * Create a new company.
     *
     * @param array $data
     * @return Company
     */
    public function create(array $data): Company;

    /**
     * Update company.
     *
     * @param Company $company
     * @param array $data
     * @return bool
     */
    public function update(Company $company, array $data): bool;

    /**
     * Soft delete company.
     *
     * @param Company $company
     * @return bool
     */
    public function delete(Company $company): bool;

    /**
     * Update company status.
     *
     * @param Company $company
     * @param string $status
     * @return bool
     */
    public function updateStatus(Company $company, string $status): bool;
}
