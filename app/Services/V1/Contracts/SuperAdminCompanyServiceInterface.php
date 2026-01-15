<?php

namespace App\Services\V1\Contracts;

use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SuperAdminCompanyServiceInterface
{
    /**
     * Get all companies with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllCompanies(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get company by ID or UUID.
     *
     * @param string|int $identifier
     * @return Company
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getCompany(string|int $identifier): Company;

    /**
     * Create a new company.
     *
     * @param array $data
     * @return Company
     */
    public function createCompany(array $data): Company;

    /**
     * Update company.
     *
     * @param Company $company
     * @param array $data
     * @return Company
     */
    public function updateCompany(Company $company, array $data): Company;

    /**
     * Soft delete company.
     *
     * @param Company $company
     * @return bool
     */
    public function deleteCompany(Company $company): bool;

    /**
     * Suspend company.
     *
     * @param Company $company
     * @return Company
     */
    public function suspendCompany(Company $company): Company;

    /**
     * Activate company.
     *
     * @param Company $company
     * @return Company
     */
    public function activateCompany(Company $company): Company;
}
