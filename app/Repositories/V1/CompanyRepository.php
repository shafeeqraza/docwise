<?php

namespace App\Repositories\V1;

use App\Models\Company;
use App\Repositories\V1\Contracts\CompanyRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CompanyRepository implements CompanyRepositoryInterface
{
    /**
     * Get all companies with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Company::query();

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['subscription_plan'])) {
            $query->where('subscription_plan', $filters['subscription_plan']);
        }

        if (isset($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Order by created_at desc by default
        $query->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Find company by ID.
     *
     * @param int $id
     * @return Company|null
     */
    public function findById(int $id): ?Company
    {
        return Company::find($id);
    }

    /**
     * Find company by UUID.
     *
     * @param string $uuid
     * @return Company|null
     */
    public function findByUuid(string $uuid): ?Company
    {
        return Company::where('uuid', $uuid)->first();
    }

    /**
     * Find company by slug.
     *
     * @param string $slug
     * @return Company|null
     */
    public function findBySlug(string $slug): ?Company
    {
        return Company::where('slug', $slug)->first();
    }

    /**
     * Create a new company.
     *
     * @param array $data
     * @return Company
     */
    public function create(array $data): Company
    {
        return Company::create($data);
    }

    /**
     * Update company.
     *
     * @param Company $company
     * @param array $data
     * @return bool
     */
    public function update(Company $company, array $data): bool
    {
        return $company->update($data);
    }

    /**
     * Soft delete company.
     *
     * @param Company $company
     * @return bool
     */
    public function delete(Company $company): bool
    {
        return $company->delete();
    }

    /**
     * Update company status.
     *
     * @param Company $company
     * @param string $status
     * @return bool
     */
    public function updateStatus(Company $company, string $status): bool
    {
        return $company->update(['status' => $status]);
    }
}
