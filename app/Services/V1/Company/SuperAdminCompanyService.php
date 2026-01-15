<?php

namespace App\Services\V1\Company;

use App\Services\V1\Contracts\SuperAdminCompanyServiceInterface;
use App\Models\Company;
use App\Repositories\V1\Contracts\AdminActionRepositoryInterface;
use App\Repositories\V1\Contracts\CompanyRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class SuperAdminCompanyService implements SuperAdminCompanyServiceInterface
{
    /**
     * Create a new service instance.
     *
     * @param CompanyRepositoryInterface $companyRepository
     * @param AdminActionRepositoryInterface $adminActionRepository
     */
    public function __construct(
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly AdminActionRepositoryInterface $adminActionRepository
    ) {}

    /**
     * Get all companies with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllCompanies(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->companyRepository->getAll($filters, $perPage);
    }

    /**
     * Get company by ID or UUID.
     *
     * @param string|int $identifier
     * @return Company
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getCompany(string|int $identifier): Company
    {
        if (is_numeric($identifier)) {
            $company = $this->companyRepository->findById((int) $identifier);
        } else {
            $company = $this->companyRepository->findByUuid($identifier);
        }

        if (!$company) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Company not found');
        }

        return $company;
    }

    /**
     * Create a new company.
     *
     * @param array $data
     * @return Company
     */
    public function createCompany(array $data): Company
    {
        // Ensure slug is generated if not provided
        if (empty($data['slug']) && !empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        // Set default values if not provided
        $data['status'] = $data['status'] ?? 'active';
        $data['subscription_plan'] = $data['subscription_plan'] ?? 'basic';
        $data['billing_cycle'] = $data['billing_cycle'] ?? 'monthly';
        $data['payment_status'] = $data['payment_status'] ?? 'active';
        $data['allow_overages'] = $data['allow_overages'] ?? false;

        return $this->companyRepository->create($data);
    }

    /**
     * Update company.
     *
     * @param Company $company
     * @param array $data
     * @return Company
     */
    public function updateCompany(Company $company, array $data): Company
    {
        // Update slug if name is being changed
        if (isset($data['name']) && $data['name'] !== $company->name) {
            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }
        }

        $this->companyRepository->update($company, $data);
        $company->refresh();

        return $company;
    }

    /**
     * Soft delete company.
     *
     * @param Company $company
     * @return bool
     */
    public function deleteCompany(Company $company): bool
    {
        return $this->companyRepository->delete($company);
    }

    /**
     * Suspend company.
     *
     * @param Company $company
     * @return Company
     */
    public function suspendCompany(Company $company): Company
    {
        $this->companyRepository->updateStatus($company, 'suspended');
        $company->refresh();

        return $company;
    }

    /**
     * Activate company.
     *
     * @param Company $company
     * @return Company
     */
    public function activateCompany(Company $company): Company
    {
        $this->companyRepository->updateStatus($company, 'active');
        $company->refresh();

        return $company;
    }
}
