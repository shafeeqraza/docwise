<?php

namespace App\Http\Controllers\V1\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\Concerns\ResponseHandler;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperAdminImpersonationController extends Controller
{
    use ResponseHandler;
    /**
     * Get list of companies available for impersonation.
     *
     * This is a helper endpoint - actual impersonation happens via X-Company-Id header.
     */
    public function getCompanies(Request $request): JsonResponse
    {
        $companies = Company::where('status', 'active')
            ->select('id', 'uuid', 'name', 'slug', 'status', 'email')
            ->orderBy('name')
            ->get();

        return $this->respondSuccess(
            [
                'companies' => $companies,
                'instructions' => [
                    'method' => 'header',
                    'header_name' => 'X-Company-Id',
                    'description' => 'Include X-Company-Id header with company ID in subsequent API requests to impersonate that company',
                ],
            ],
            'Companies available for impersonation'
        );
    }

    /**
     * Validate and get company details for impersonation.
     *
     * Helper endpoint to verify a company exists before using it in header.
     */
    public function validateCompany(Request $request, $companyId): JsonResponse
    {
        $user = $request->user();

        if (!$user->isSuperAdmin()) {
            return $this->respondError(
                'Only super admins can impersonate',
                403
            );
        }

        $company = Company::findOrFail($companyId);

        return $this->respondSuccess(
            [
                'company' => [
                    'id' => $company->id,
                    'uuid' => $company->uuid,
                    'name' => $company->name,
                    'slug' => $company->slug,
                    'status' => $company->status,
                ],
                'usage' => [
                    'header' => 'X-Company-Id',
                    'value' => $company->id,
                    'example_curl' => "curl -H 'X-Company-Id: {$company->id}' ...",
                    'example_js' => "headers: { 'X-Company-Id': '{$company->id}' }",
                ],
            ],
            'Company is available for impersonation'
        );
    }

    /**
     * Get current impersonation status.
     *
     * Returns the company ID from X-Company-Id header if present.
     */
    public function getStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isSuperAdmin()) {
            return $this->respondError('Only super admins can check impersonation status', 403);
        }

        $companyId = $request->header('X-Company-Id');

        return $this->handleStatusResponse($companyId);
    }

    /**
     * Handle status response based on company ID.
     */
    private function handleStatusResponse(?string $companyId): JsonResponse
    {
        if (!$companyId) {
            return $this->respondSuccess(
                [
                    'impersonating' => false,
                    'instructions' => 'Include X-Company-Id header to impersonate a company',
                ],
                'Not currently impersonating any company'
            );
        }

        $company = Company::find($companyId);

        if (!$company) {
            return $this->respondError(
                'Company not found',
                404,
                [],
                ['company_id' => $companyId]
            );
        }

        return $this->respondSuccess(
            [
                'impersonating' => true,
                'company' => [
                    'id' => $company->id,
                    'uuid' => $company->uuid,
                    'name' => $company->name,
                    'slug' => $company->slug,
                ],
            ],
            'Currently impersonating company'
        );
    }
}
