<?php

namespace App\Http\Controllers\V1\Api;

use App\Contracts\V1\ApiKeyServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\Concerns\ResponseHandler;
use App\Http\Requests\CreateApiKeyRequest;
use App\Http\Requests\UpdateApiKeyRequest;
use App\Http\Resources\ApiKeyResource;
use App\Models\Company;
use App\Models\CompanyApiKey;
use App\Services\V1\Common\PaginatedResponseFormatter;
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
        $filters = [
            'is_active' => $isActive !== null ? filter_var($isActive, FILTER_VALIDATE_BOOLEAN) : null,
            'search' => $request->query('search'),
        ];

        // Remove null values
        $filters = array_filter($filters, fn($value) => $value !== null);

        $perPage = (int) $request->query('per_page', 15);
        $perPage = min(max($perPage, 1), 100);

        $apiKeys = $this->apiKeyService->getAllForCompany($company, $filters, $perPage);
        $formattedResponse = PaginatedResponseFormatter::formatWithResource($apiKeys, ApiKeyResource::class);
        return $this->respondSuccess($formattedResponse);
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
        $apiKeyModel = $this->apiKeyService->getApiKey($company, $apiKey);

        return $this->respondResource(
            new ApiKeyResource($apiKeyModel),
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

            $result = $this->apiKeyService->createApiKey(
                $company,
                $user,
                $request->validated()
            );

            DB::commit();

            return $this->respondSuccess([
                'data' => new ApiKeyResource($result['apiKey']),
                'key' => $result['plainKey'], // Only shown once
            ], 'API key created successfully. Please save this key securely - it will not be shown again.', 201);
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
            $apiKeyModel = $this->apiKeyService->getApiKey($company, $apiKey);

            $updatedApiKey = $this->apiKeyService->updateApiKey(
                $apiKeyModel,
                $request->validated()
            );

            DB::commit();

            return $this->respondResource(
                new ApiKeyResource($updatedApiKey),
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
            $apiKeyModel = $this->apiKeyService->getApiKey($company, $apiKey);

            $this->apiKeyService->deleteApiKey($apiKeyModel);
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
            $apiKeyModel = $this->apiKeyService->getApiKey($company, $apiKey);

            $result = $this->apiKeyService->regenerateApiKey($apiKeyModel);
            DB::commit();

            return $this->respondSuccess([
                'data' => new ApiKeyResource($result['apiKey']),
                'key' => $result['plainKey'], // Only shown once
            ], 'API key regenerated successfully. Please save this key securely - it will not be shown again.');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
