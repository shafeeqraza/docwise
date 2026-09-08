<?php

namespace App\Http\Controllers\V1\Api;

use App\Enums\CompanyBillingCycle;
use App\Enums\CompanyPaymentStatus;
use App\Enums\CompanyStatus;
use App\Services\V1\Contracts\SuperAdminCompanyServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\Concerns\ResponseHandler;
use App\Http\Requests\CreateCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Repositories\V1\Contracts\AdminActionRepositoryInterface;
use App\Services\V1\DTOs\CreateCompanyDTO;
use App\Services\V1\DTOs\GetCompanyDTO;
use App\Services\V1\DTOs\ListCompaniesDTO;
use App\Services\V1\DTOs\UpdateCompanyDTO;
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
        $perPage = (int) $request->query('per_page', 15);
        $perPage = min(max($perPage, 1), 100); // Limit between 1 and 100

        $dto = new ListCompaniesDTO(
            status: CompanyStatus::tryFrom((string) $request->query('status')),
            subscriptionPlan: $request->query('subscription_plan'),
            paymentStatus: CompanyPaymentStatus::tryFrom((string) $request->query('payment_status')),
            search: $request->query('search'),
            perPage: $perPage
        );

        $resource = $this->companyService->getAllCompanies($dto);

        return $this->respondResource($resource, 'Companies retrieved successfully');
    }

    /**
     * Get company by ID or UUID.
     *
     * @param string|int $company
     * @return JsonResponse
     */
    public function show(string|int $company): JsonResponse
    {
        $dto = new GetCompanyDTO(identifier: $company);
        $companyResource = $this->companyService->getCompany($dto);

        return $this->respondResource(
            $companyResource,
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
            $validated = $request->validated();

            $dto = new CreateCompanyDTO(
                name: $validated['name'],
                slug: $validated['slug'] ?? null,
                email: $validated['email'] ?? null,
                phone: $validated['phone'] ?? null,
                status: CompanyStatus::from($validated['status'] ?? CompanyStatus::ACTIVE->value),
                subscriptionPlan: $validated['subscription_plan'] ?? 'basic',
                billingCycle: CompanyBillingCycle::from($validated['billing_cycle'] ?? CompanyBillingCycle::MONTHLY->value),
                paymentStatus: CompanyPaymentStatus::from($validated['payment_status'] ?? CompanyPaymentStatus::ACTIVE->value),
                allowOverages: $validated['allow_overages'] ?? false,
                settings: $validated['settings'] ?? null
            );

            $companyResource = $this->companyService->createCompany($dto);
            DB::commit();

            // Log admin action
            $this->adminActionRepository->logAction(
                user: $user,
                action: 'company.create',
                targetCompanyId: $companyResource->id,
                details: [
                    'company_name' => $companyResource->name,
                    'company_uuid' => $companyResource->uuid,
                    'subscription_plan' => $companyResource->subscription_plan,
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return $this->respondResource(
                $companyResource,
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

            // Get company ID first
            $getDto = new GetCompanyDTO(identifier: $company);
            $companyResource = $this->companyService->getCompany($getDto);
            $companyId = $companyResource->id;

            $oldData = [
                'name' => $companyResource->name,
                'status' => $companyResource->status?->value,
                'subscription_plan' => $companyResource->subscription_plan,
            ];

            $validated = $request->validated();
            $updateDto = new UpdateCompanyDTO(
                name: $validated['name'] ?? null,
                slug: $validated['slug'] ?? null,
                email: $validated['email'] ?? null,
                phone: $validated['phone'] ?? null,
                status: CompanyStatus::tryFrom($validated['status'] ?? ''),
                subscriptionPlan: $validated['subscription_plan'] ?? null,
                billingCycle: CompanyBillingCycle::tryFrom($validated['billing_cycle'] ?? ''),
                paymentStatus: CompanyPaymentStatus::tryFrom($validated['payment_status'] ?? ''),
                allowOverages: $validated['allow_overages'] ?? null,
                settings: $validated['settings'] ?? null
            );

            $updatedCompany = $this->companyService->updateCompany($companyId, $updateDto);
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
                        'status' => $updatedCompany->status?->value,
                        'subscription_plan' => $updatedCompany->subscription_plan,
                    ],
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return $this->respondResource(
                $updatedCompany,
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

            // Get company ID first
            $getDto = new GetCompanyDTO(identifier: $company);
            $companyResource = $this->companyService->getCompany($getDto);
            $companyId = $companyResource->id;

            $this->companyService->deleteCompany($companyId);
            DB::commit();

            // Log admin action
            $this->adminActionRepository->logAction(
                user: $user,
                action: 'company.delete',
                targetCompanyId: $companyId,
                details: [
                    'company_name' => $companyResource->name,
                    'company_uuid' => $companyResource->uuid,
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

            // Get company ID first
            $getDto = new GetCompanyDTO(identifier: $company);
            $companyResource = $this->companyService->getCompany($getDto);
            $companyId = $companyResource->id;

            $updatedCompany = $this->companyService->suspendCompany($companyId);
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
                $updatedCompany,
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

            // Get company ID first
            $getDto = new GetCompanyDTO(identifier: $company);
            $companyResource = $this->companyService->getCompany($getDto);
            $companyId = $companyResource->id;

            $updatedCompany = $this->companyService->activateCompany($companyId);
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
                $updatedCompany,
                'Company activated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
