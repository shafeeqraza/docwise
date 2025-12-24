<?php

namespace App\Http\Controllers\V1\Api;

use App\Contracts\V1\SuperAdminCompanyServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\Concerns\ResponseHandler;
use App\Http\Requests\CreateCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Repositories\V1\AdminActionRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperAdminCompanyController extends Controller
{
    use ResponseHandler;

    /**
     * Create a new controller instance.
     *
     * @param SuperAdminCompanyServiceInterface $companyService
     * @param AdminActionRepositoryInterface $adminActionRepository
     */
    public function __construct(
        private readonly SuperAdminCompanyServiceInterface $companyService,
        private readonly AdminActionRepositoryInterface $adminActionRepository
    ) {}

    /**
     * Get all companies with pagination.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'status' => $request->query('status'),
            'subscription_plan' => $request->query('subscription_plan'),
            'payment_status' => $request->query('payment_status'),
            'search' => $request->query('search'),
        ];

        // Remove null values
        $filters = array_filter($filters, fn($value) => $value !== null);

        $perPage = (int) $request->query('per_page', 15);
        $perPage = min(max($perPage, 1), 100); // Limit between 1 and 100

        $companies = $this->companyService->getAllCompanies($filters, $perPage);

        return $this->respondSuccess([
            'data' => CompanyResource::collection($companies->items()),
            'meta' => [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'per_page' => $companies->perPage(),
                'total' => $companies->total(),
            ],
            'links' => [
                'first' => $companies->url(1),
                'last' => $companies->url($companies->lastPage()),
                'prev' => $companies->previousPageUrl(),
                'next' => $companies->nextPageUrl(),
            ],
        ], 'Companies retrieved successfully');
    }

    /**
     * Get company by ID or UUID.
     *
     * @param string|int $company
     * @return JsonResponse
     */
    public function show(string|int $company): JsonResponse
    {
        $companyModel = $this->companyService->getCompany($company);
        return $this->respondResource(
            new CompanyResource($companyModel),
            'Company retrieved successfully'
        );
    }

    /**
     * Create a new company.
     *
     * @param CreateCompanyRequest $request
     * @return JsonResponse
     */
    public function store(CreateCompanyRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $user = $request->user();
            $company = $this->companyService->createCompany($request->validated());
            DB::commit();

            // Log admin action
            $this->adminActionRepository->logAction(
                user: $user,
                action: 'company.create',
                targetCompanyId: $company->id,
                details: [
                    'company_name' => $company->name,
                    'company_uuid' => $company->uuid,
                    'subscription_plan' => $company->subscription_plan,
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );
            return $this->respondResource(
                new CompanyResource($company),
                'Company created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update company.
     *
     * @param UpdateCompanyRequest $request
     * @param string|int $company
     * @return JsonResponse
     */
    public function update(UpdateCompanyRequest $request, string|int $company): JsonResponse
    {
        try {
            DB::beginTransaction();
            $user = $request->user();
            $companyModel = $this->companyService->getCompany($company);

            $oldData = [
                'name' => $companyModel->name,
                'status' => $companyModel->status,
                'subscription_plan' => $companyModel->subscription_plan,
            ];

            $updatedCompany = $this->companyService->updateCompany(
                $companyModel,
                $request->validated()
            );
            DB::commit();

            // Log admin action
            $this->adminActionRepository->logAction(
                user: $user,
                action: 'company.update',
                targetCompanyId: $updatedCompany->id,
                details: [
                    'company_uuid' => $updatedCompany->uuid,
                    'old_data' => $oldData,
                    'new_data' => [
                        'name' => $updatedCompany->name,
                        'status' => $updatedCompany->status,
                        'subscription_plan' => $updatedCompany->subscription_plan,
                    ],
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return $this->respondResource(
                new CompanyResource($updatedCompany),
                'Company updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Soft delete company.
     *
     * @param Request $request
     * @param string|int $company
     * @return JsonResponse
     */
    public function destroy(Request $request, string|int $company): JsonResponse
    {
        try {
            DB::beginTransaction();
            $user = $request->user();
            $companyModel = $this->companyService->getCompany($company);

            $this->companyService->deleteCompany($companyModel);
            DB::commit();

            // Log admin action
            $this->adminActionRepository->logAction(
                user: $user,
                action: 'company.delete',
                targetCompanyId: $companyModel->id,
                details: [
                    'company_name' => $companyModel->name,
                    'company_uuid' => $companyModel->uuid,
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return $this->respondMessage('Company deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Suspend company.
     *
     * @param Request $request
     * @param string|int $company
     * @return JsonResponse
     */
    public function suspend(Request $request, string|int $company): JsonResponse
    {
        try {
            DB::beginTransaction();
            $user = $request->user();
            $companyModel = $this->companyService->getCompany($company);

            $updatedCompany = $this->companyService->suspendCompany($companyModel);
            DB::commit();

            // Log admin action
            $this->adminActionRepository->logAction(
                user: $user,
                action: 'company.suspend',
                targetCompanyId: $updatedCompany->id,
                details: [
                    'company_name' => $updatedCompany->name,
                    'company_uuid' => $updatedCompany->uuid,
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return $this->respondResource(
                new CompanyResource($updatedCompany),
                'Company suspended successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Activate company.
     *
     * @param Request $request
     * @param string|int $company
     * @return JsonResponse
     */
    public function activate(Request $request, string|int $company): JsonResponse
    {
        try {
            DB::beginTransaction();
            $user = $request->user();
            $companyModel = $this->companyService->getCompany($company);

            $updatedCompany = $this->companyService->activateCompany($companyModel);
            DB::commit();

            // Log admin action
            $this->adminActionRepository->logAction(
                user: $user,
                action: 'company.activate',
                targetCompanyId: $updatedCompany->id,
                details: [
                    'company_name' => $updatedCompany->name,
                    'company_uuid' => $updatedCompany->uuid,
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return $this->respondResource(
                new CompanyResource($updatedCompany),
                'Company activated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
