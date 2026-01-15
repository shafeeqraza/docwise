<?php

namespace App\Http\Middleware;

use App\Services\V1\Contracts\ApiKeyServiceInterface;
use App\Exceptions\AuthenticationException;
use App\Services\V1\Company\ApiKeyUsageService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    /**
     * Create a new middleware instance.
     *
     * @param ApiKeyServiceInterface $apiKeyService
     * @param ApiKeyUsageService $usageService
     */
    public function __construct(
        private readonly ApiKeyServiceInterface $apiKeyService,
        private readonly ApiKeyUsageService $usageService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get API key from Authorization header or X-API-Key header
        $apiKey = $this->extractApiKey($request);

        if (!$apiKey) {
            throw new AuthenticationException('API key is required. Please provide it in the Authorization header as "Bearer {key}" or in the X-API-Key header.', 401);
        }

        // Validate API key
        $apiKeyModel = $this->apiKeyService->validateApiKey($apiKey);

        if (!$apiKeyModel) {
            throw new AuthenticationException('Invalid or expired API key.', 401);
        }

        // Check if company is active
        if (!$apiKeyModel->company->isActive()) {
            throw new AuthenticationException('Company account is not active.', 403);
        }

        // Set company context for the request
        $request->attributes->add(['current_company_id' => $apiKeyModel->company_id]);
        $request->attributes->add(['api_key' => $apiKeyModel]);
        $request->attributes->add(['authenticated_company' => $apiKeyModel->company]);

        // Check permissions if needed
        $requiredPermission = $request->route()?->getAction('permission');
        if ($requiredPermission && !$apiKeyModel->hasPermission($requiredPermission)) {
            throw new AuthenticationException('API key does not have the required permission: ' . $requiredPermission, 403);
        }

        // Check rate limits
        $rateLimitCheck = $this->usageService->checkRateLimit($apiKeyModel);
        if (!$rateLimitCheck['allowed']) {
            throw AuthenticationException::tooManyAttempts(0);
        }

        // Track usage
        $this->usageService->trackUsage($apiKeyModel);

        return $next($request);
    }

    /**
     * Extract API key from request headers.
     *
     * @param Request $request
     * @return string|null
     */
    private function extractApiKey(Request $request): ?string
    {
        // Try Authorization header first (Bearer token format)
        $authHeader = $request->header('Authorization');
        if ($authHeader && preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            return trim($matches[1]);
        }

        // Try X-API-Key header
        $apiKeyHeader = $request->header('X-API-Key');
        if ($apiKeyHeader) {
            return trim($apiKeyHeader);
        }

        return null;
    }
}
