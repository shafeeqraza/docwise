<?php

namespace App\Services\V1\Company;

use App\Enums\CompanyStatus;
use App\Services\V1\Contracts\SuperAdminCompanyServiceInterface;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\Repositories\V1\Contracts\AdminActionRepositoryInterface;
use App\Repositories\V1\Contracts\CompanyRepositoryInterface;
use App\Services\V1\DTOs\CreateCompanyDTO;
use App\Services\V1\DTOs\GetCompanyDTO;
use App\Services\V1\DTOs\ListCompaniesDTO;
use App\Services\V1\DTOs\UpdateCompanyDTO;
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
     * @param ListCompaniesDTO $dto
     * @return \App\Http\Resources\PaginatedResourceCollection
     */
    public function getAllCompanies(ListCompaniesDTO $dto): PaginatedResourceCollection
    {
        $filters = array_filter([
            'status' => $dto->status,
            'subscription_plan' => $dto->subscriptionPlan,
            'payment_status' => $dto->paymentStatus,
            'search' => $dto->search,
        ], fn($value) => $value !== null);

        $companies = $this->companyRepository->getAll($filters, $dto->perPage);

        return new PaginatedResourceCollection(
            CompanyResource::collection($companies->items()),
            $companies
        );
    }

    /**
     * Get company by ID or UUID.
     *
     * @param GetCompanyDTO $dto
     * @return CompanyResource
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getCompany(GetCompanyDTO $dto): CompanyResource
    {
        if (is_numeric($dto->identifier)) {
            $company = $this->companyRepository->findById((int) $dto->identifier);
        } else {
            $company = $this->companyRepository->findByUuid($dto->identifier);
        }

        if (!$company) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Company not found');
        }

        return new CompanyResource($company);
    }

    /**
     * Create a new company.
     *
     * @param CreateCompanyDTO $dto
     * @return CompanyResource
     */
    public function createCompany(CreateCompanyDTO $dto): CompanyResource
    {
        $slug = $dto->slug ?? Str::slug($dto->name);

        $data = [
            'name' => $dto->name,
            'slug' => $slug,
            'email' => $dto->email,
            'phone' => $dto->phone,
            'status' => $dto->status,
            'subscription_plan' => $dto->subscriptionPlan,
            'billing_cycle' => $dto->billingCycle,
            'payment_status' => $dto->paymentStatus,
            'allow_overages' => $dto->allowOverages,
            'settings' => $dto->settings,
        ];

        $company = $this->companyRepository->create($data);

        return new CompanyResource($company);
    }

    /**
     * Update company.
     *
     * @param int $companyId
     * @param UpdateCompanyDTO $dto
     * @return CompanyResource
     */
    public function updateCompany(int $companyId, UpdateCompanyDTO $dto): CompanyResource
    {
        $company = $this->companyRepository->findById($companyId);
        if (!$company) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Company not found');
        }

        $updateData = array_filter([
            'name' => $dto->name,
            'slug' => $dto->slug ?? ($dto->name ? Str::slug($dto->name) : null),
            'email' => $dto->email,
            'phone' => $dto->phone,
            'status' => $dto->status,
            'subscription_plan' => $dto->subscriptionPlan,
            'billing_cycle' => $dto->billingCycle,
            'payment_status' => $dto->paymentStatus,
            'allow_overages' => $dto->allowOverages,
            'settings' => $dto->settings,
        ], fn($value) => $value !== null);

        // Update slug if name is being changed
        if (isset($updateData['name']) && $updateData['name'] !== $company->name && !isset($updateData['slug'])) {
            $updateData['slug'] = Str::slug($updateData['name']);
        }

        $this->companyRepository->update($company, $updateData);
        $company->refresh();

        return new CompanyResource($company);
    }

    /**
     * Soft delete company.
     *
     * @param int $companyId
     * @return bool
     */
    public function deleteCompany(int $companyId): bool
    {
        $company = $this->companyRepository->findById($companyId);
        if (!$company) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Company not found');
        }

        return $this->companyRepository->delete($company);
    }

    /**
     * Suspend company.
     *
     * @param int $companyId
     * @return CompanyResource
     */
    public function suspendCompany(int $companyId): CompanyResource
    {
        $company = $this->companyRepository->findById($companyId);
        if (!$company) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Company not found');
        }

        $this->companyRepository->updateStatus($company, CompanyStatus::SUSPENDED);
        $company->refresh();

        return new CompanyResource($company);
    }

    /**
     * Activate company.
     *
     * @param int $companyId
     * @return CompanyResource
     */
    public function activateCompany(int $companyId): CompanyResource
    {
        $company = $this->companyRepository->findById($companyId);
        if (!$company) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Company not found');
        }

        $this->companyRepository->updateStatus($company, CompanyStatus::ACTIVE);
        $company->refresh();

        return new CompanyResource($company);
    }
}
