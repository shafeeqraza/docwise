<?php

namespace App\Http\Controllers\V1\Api;

use App\Services\V1\Contracts\ApiKeyServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\Concerns\ResponseHandler;
use App\Http\Requests\CreateApiKeyRequest;
use App\Http\Requests\UpdateApiKeyRequest;
use App\Models\CompanyApiKey;
use App\Services\V1\DTOs\CreateApiKeyDTO;
use App\Services\V1\DTOs\GetApiKeyDTO;
use App\Services\V1\DTOs\ListApiKeysDTO;
use App\Services\V1\DTOs\UpdateApiKeyDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiKeyController extends Controller
{
    use ResponseHandler;

    /**
     * Create a new controller instance.
     *
     * @param ApiKeyServiceInterface $apiKeyService
     */
    public function __construct(
        private readonly ApiKeyServiceInterface $apiKeyService
    ) {}

    /**
     * Get all API keys for the current company.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CompanyApiKey::class);

        $companyId = $request->attributes->get('current_company_id');

        $isActive = $request->query('is_active');
        $isActiveBool = $isActive !== null ? filter_var($isActive, FILTER_VALIDATE_BOOLEAN) : null;

        $perPage = (int) $request->query('per_page', 15);
        $perPage = min(max($perPage, 1), 100);

        $dto = new ListApiKeysDTO(
            companyId: $companyId,
            isActive: $isActiveBool,
            search: $request->query('search'),
            perPage: $perPage
        );

        $resource = $this->apiKeyService->getAllForCompany($dto);
        return $this->respondResource($resource, 'API keys retrieved successfully');
    }

    /**
     * Get API key by ID or UUID.
     *
     * @param Request $request
     * @param string|int $apiKey
     * @return JsonResponse
     */
    public function show(Request $request, string|int $apiKey): JsonResponse
    {
        $apiKeyResource = $this->apiKeyService->getApiKey(new GetApiKeyDTO(
            companyId: $request->attributes->get('current_company_id'),
            identifier: $apiKey
        ));
        $this->authorize('view', $apiKeyResource->resource);

        return $this->respondResource(
            $apiKeyResource,
            'API key retrieved successfully'
        );
    }

    /**
     * Create a new API key.
     *
     * @param CreateApiKeyRequest $request
     * @return JsonResponse
     */
    public function store(CreateApiKeyRequest $request): JsonResponse
    {
        $this->authorize('create', CompanyApiKey::class);

        try {
            DB::beginTransaction();
            $companyId = $request->attributes->get('current_company_id');
            $user = $request->user();
            $validated = $request->validated();

            $dto = new CreateApiKeyDTO(
                companyId: $companyId,
                userId: $user->id,
                name: $validated['name'],
                allowedDomain: $validated['allowed_domain'],
                rateLimitPerMinute: $validated['rate_limit_per_minute'] ?? null,
                rateLimitPerHour: $validated['rate_limit_per_hour'] ?? null,
                isActive: $validated['is_active'] ?? true,
                expiresAt: $validated['expires_at'] ?? null
            );

            $resource = $this->apiKeyService->createApiKey($dto);

            DB::commit();

            return $this->respondResource(
                $resource,
                'API key created successfully. Please save this key securely - it will not be shown again.',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update API key.
     *
     * @param UpdateApiKeyRequest $request
     * @param string|int $apiKey
     * @return JsonResponse
     */
    public function update(UpdateApiKeyRequest $request, string|int $apiKey): JsonResponse
    {
        $apiKeyResource = $this->apiKeyService->getApiKey(new GetApiKeyDTO(
            companyId: $request->attributes->get('current_company_id'),
            identifier: $apiKey
        ));
        $this->authorize('update', $apiKeyResource->resource);

        try {
            DB::beginTransaction();

            $validated = $request->validated();
            $updateDto = new UpdateApiKeyDTO(
                name: $validated['name'] ?? null,
                allowedDomain: $validated['allowed_domain'] ?? null,
                rateLimitPerMinute: $validated['rate_limit_per_minute'] ?? null,
                rateLimitPerHour: $validated['rate_limit_per_hour'] ?? null,
                isActive: $validated['is_active'] ?? null,
                expiresAt: $validated['expires_at'] ?? null
            );

            $updatedApiKey = $this->apiKeyService->updateApiKey($apiKeyResource->id, $updateDto);

            DB::commit();

            return $this->respondResource(
                $updatedApiKey,
                'API key updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete (revoke) API key.
     *
     * @param Request $request
     * @param string|int $apiKey
     * @return JsonResponse
     */
    public function destroy(Request $request, string|int $apiKey): JsonResponse
    {
        $apiKeyResource = $this->apiKeyService->getApiKey(new GetApiKeyDTO(
            companyId: $request->attributes->get('current_company_id'),
            identifier: $apiKey
        ));
        $this->authorize('delete', $apiKeyResource->resource);

        try {
            DB::beginTransaction();
            $this->apiKeyService->deleteApiKey($apiKeyResource->id);
            DB::commit();

            return $this->respondMessage('API key revoked successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Regenerate API key.
     *
     * @param Request $request
     * @param string|int $apiKey
     * @return JsonResponse
     */
    public function regenerate(Request $request, string|int $apiKey): JsonResponse
    {
        $apiKeyResource = $this->apiKeyService->getApiKey(new GetApiKeyDTO(
            companyId: $request->attributes->get('current_company_id'),
            identifier: $apiKey
        ));
        $this->authorize('regenerate', $apiKeyResource->resource);

        try {
            DB::beginTransaction();
            $resource = $this->apiKeyService->regenerateApiKey($apiKeyResource->id);
            DB::commit();

            return $this->respondResource(
                $resource,
                'API key regenerated successfully. Please save this key securely - it will not be shown again.'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
