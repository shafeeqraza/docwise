<?php

namespace App\Services\V1\Contracts;

use App\Http\Resources\CompanyResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\Services\V1\DTOs\CreateCompanyDTO;
use App\Services\V1\DTOs\GetCompanyDTO;
use App\Services\V1\DTOs\ListCompaniesDTO;
use App\Services\V1\DTOs\UpdateCompanyDTO;

interface SuperAdminCompanyServiceInterface
{
    /**
     * Get all companies with pagination.
     *
     * @param ListCompaniesDTO $dto
     * @return \App\Http\Resources\PaginatedResourceCollection
     */
    public function getAllCompanies(ListCompaniesDTO $dto): PaginatedResourceCollection;

    /**
     * Get company by ID or UUID.
     *
     * @param GetCompanyDTO $dto
     * @return CompanyResource
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getCompany(GetCompanyDTO $dto): CompanyResource;

    /**
     * Create a new company.
     *
     * @param CreateCompanyDTO $dto
     * @return CompanyResource
     */
    public function createCompany(CreateCompanyDTO $dto): CompanyResource;

    /**
     * Update company.
     *
     * @param int $companyId
     * @param UpdateCompanyDTO $dto
     * @return CompanyResource
     */
    public function updateCompany(int $companyId, UpdateCompanyDTO $dto): CompanyResource;

    /**
     * Soft delete company.
     *
     * @param int $companyId
     * @return bool
     */
    public function deleteCompany(int $companyId): bool;

    /**
     * Suspend company.
     *
     * @param int $companyId
     * @return CompanyResource
     */
    public function suspendCompany(int $companyId): CompanyResource;

    /**
     * Activate company.
     *
     * @param int $companyId
     * @return CompanyResource
     */
    public function activateCompany(int $companyId): CompanyResource;
}
