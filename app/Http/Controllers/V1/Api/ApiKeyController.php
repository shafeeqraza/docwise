<?php

namespace App\Http\Controllers\V1\Api;

use App\Services\V1\Contracts\ApiKeyServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\Concerns\ResponseHandler;
use App\Http\Requests\CreateApiKeyRequest;
use App\Http\Requests\UpdateApiKeyRequest;
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
     * Get all API keys for the authenticated user's company.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;

        $isActive = $request->query('is_active');
        $isActiveBool = $isActive !== null ? filter_var($isActive, FILTER_VALIDATE_BOOLEAN) : null;

        $perPage = (int) $request->query('per_page', 15);
        $perPage = min(max($perPage, 1), 100);

        $dto = new ListApiKeysDTO(
            companyId: $company->id,
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
        $company = $request->user()->company;

        $dto = new GetApiKeyDTO(
            companyId: $company->id,
            identifier: $apiKey
        );

        $apiKeyResource = $this->apiKeyService->getApiKey($dto);

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
        try {
            DB::beginTransaction();
            $company = $request->user()->company;
            $user = $request->user();
            $validated = $request->validated();

            $dto = new CreateApiKeyDTO(
                companyId: $company->id,
                userId: $user->id,
                name: $validated['name'],
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
        try {
            DB::beginTransaction();
            $company = $request->user()->company;

            // Get API key ID first
            $getDto = new GetApiKeyDTO(
                companyId: $company->id,
                identifier: $apiKey
            );
            $apiKeyResource = $this->apiKeyService->getApiKey($getDto);
            $apiKeyId = $apiKeyResource->id;

            $validated = $request->validated();
            $updateDto = new UpdateApiKeyDTO(
                name: $validated['name'] ?? null,
                rateLimitPerMinute: $validated['rate_limit_per_minute'] ?? null,
                rateLimitPerHour: $validated['rate_limit_per_hour'] ?? null,
                isActive: $validated['is_active'] ?? null,
                expiresAt: $validated['expires_at'] ?? null
            );

            $updatedApiKey = $this->apiKeyService->updateApiKey($apiKeyId, $updateDto);

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
        try {
            DB::beginTransaction();
            $company = $request->user()->company;

            // Get API key ID first
            $getDto = new GetApiKeyDTO(
                companyId: $company->id,
                identifier: $apiKey
            );
            $apiKeyResource = $this->apiKeyService->getApiKey($getDto);
            $apiKeyId = $apiKeyResource->id;

            $this->apiKeyService->deleteApiKey($apiKeyId);
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
        try {
            DB::beginTransaction();
            $company = $request->user()->company;

            // Get API key ID first
            $getDto = new GetApiKeyDTO(
                companyId: $company->id,
                identifier: $apiKey
            );
            $apiKeyResource = $this->apiKeyService->getApiKey($getDto);
            $apiKeyId = $apiKeyResource->id;

            $resource = $this->apiKeyService->regenerateApiKey($apiKeyId);
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
