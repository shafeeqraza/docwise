<?php

namespace App\Http\Middleware;

use App\Models\CompanyApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract API key from headers
        $apiKey = $request->bearerToken() ?? $request->header('X-API-Key');

        if (!$apiKey) {
            return response()->json([
                'error' => 'API key is required',
                'message' => 'Please provide an API key via Authorization header (Bearer token) or X-API-Key header'
            ], 401);
        }

        // Hash the API key to look it up
        $keyHash = hash('sha256', $apiKey);

        // Find the API key
        $apiKeyRecord = CompanyApiKey::where('key_hash', '=', $keyHash)->first();

        if (!$apiKeyRecord) {
            return response()->json([
                'error' => 'Invalid API key',
                'message' => 'The provided API key is invalid'
            ], 401);
        }

        // Check if key is active
        if (!$apiKeyRecord->is_active) {
            return response()->json([
                'error' => 'API key is inactive',
                'message' => 'This API key has been deactivated'
            ], 403);
        }

        // Check if key is expired
        if ($apiKeyRecord->isExpired()) {
            return response()->json([
                'error' => 'API key has expired',
                'message' => 'This API key has expired'
            ], 403);
        }

        // Validate domain restriction
        // Extract domain from Origin (preferred) or Referer header
        $origin = $request->header('Origin') ?? $request->header('Referer');

        // if ($origin) {
        //     // Validate domain from Origin/Referer header
        //     if (!$apiKeyRecord->isDomainAllowed($origin)) {
        //         return response()->json([
        //             'error' => 'Domain not allowed',
        //             'message' => 'This API key is not authorized for the requesting domain'
        //         ], 403);
        //     }
        // } else {
        //     // For direct API calls without Origin/Referer, allow if X-Allowed-Domain header is provided
        //     // This supports server-to-server API calls
        //     $domain = $request->header('X-Allowed-Domain');
        //     if ($domain && !$apiKeyRecord->isDomainAllowed($domain)) {
        //         return response()->json([
        //             'error' => 'Domain not allowed',
        //             'message' => 'This API key is not authorized for the specified domain'
        //         ], 403);
        //     }
        //     // If no origin and no X-Allowed-Domain, reject for security
        //     // Widget requests should always have Origin header
        //     if (!$domain) {
        //         return response()->json([
        //             'error' => 'Domain validation required',
        //             'message' => 'Origin header or X-Allowed-Domain header is required for domain validation'
        //         ], 403);
        //     }
        // }

        // Check permissions (if needed in the future)
        // For now, we'll allow all requests if key is valid

        // Update last used timestamp
        $apiKeyRecord->updateLastUsed();

        // Set company context in request attributes
        $request->attributes->add(['current_company_id' => $apiKeyRecord->company_id]);
        $request->attributes->add(['api_key' => $apiKeyRecord]);

        return $next($request);
    }
}
