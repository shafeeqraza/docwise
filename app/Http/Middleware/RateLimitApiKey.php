<?php

namespace App\Http\Middleware;

use App\Models\CompanyApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limiting middleware for API key requests.
 *
 * Follows Single Responsibility Principle (SRP): Only handles rate limiting.
 * Must run after AuthenticateApiKey middleware to access API key from request attributes.
 */
class RateLimitApiKey
{
    private const MINUTE_WINDOW = 60; // seconds
    private const HOUR_WINDOW = 3600; // seconds
    private const MINUTE_SUFFIX = ':minute';
    private const HOUR_SUFFIX = ':hour';

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get API key from request attributes (set by AuthenticateApiKey middleware)
        $apiKey = $request->attributes->get('api_key');

        if (!$apiKey instanceof CompanyApiKey) {
            // If API key is not set, skip rate limiting (shouldn't happen if middleware order is correct)
            return $next($request);
        }

        $rateLimitKey = 'api_key:' . $apiKey->id;
        $minuteLimit = $apiKey->rate_limit_per_minute ?? 60;
        $hourLimit = $apiKey->rate_limit_per_hour ?? 1000;

        // Check per-minute limit
        $minuteKey = $rateLimitKey . self::MINUTE_SUFFIX;
        if (RateLimiter::tooManyAttempts($minuteKey, $minuteLimit)) {
            $seconds = RateLimiter::availableIn($minuteKey);
            $remaining = max(0, $minuteLimit - RateLimiter::attempts($minuteKey));

            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $seconds,
                'limit' => $minuteLimit,
                'remaining' => $remaining,
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => $minuteLimit,
                'X-RateLimit-Remaining' => $remaining,
                'X-RateLimit-Reset' => now()->addSeconds($seconds)->getTimestamp(),
                'Retry-After' => $seconds,
            ]);
        }

        // Check per-hour limit
        $hourKey = $rateLimitKey . self::HOUR_SUFFIX;
        if (RateLimiter::tooManyAttempts($hourKey, $hourLimit)) {
            $seconds = RateLimiter::availableIn($hourKey);
            $remaining = max(0, $hourLimit - RateLimiter::attempts($hourKey));

            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => 'Hourly rate limit exceeded. Please try again later.',
                'retry_after' => $seconds,
                'limit' => $hourLimit,
                'remaining' => $remaining,
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => $hourLimit,
                'X-RateLimit-Remaining' => $remaining,
                'X-RateLimit-Reset' => now()->addSeconds($seconds)->getTimestamp(),
                'Retry-After' => $seconds,
            ]);
        }

        // Increment rate limiters
        RateLimiter::hit($minuteKey, self::MINUTE_WINDOW);
        RateLimiter::hit($hourKey, self::HOUR_WINDOW);

        // Calculate remaining attempts for response headers (after increment)
        $minuteAttempts = RateLimiter::attempts($minuteKey);
        $hourAttempts = RateLimiter::attempts($hourKey);
        $minuteRemaining = max(0, $minuteLimit - $minuteAttempts);
        $hourRemaining = max(0, $hourLimit - $hourAttempts);

        // Add rate limit headers to response
        $response = $next($request);

        // Add rate limit headers if response is successful
        if ($response->getStatusCode() < 400) {
            $response->headers->set('X-RateLimit-Limit-Minute', (string) $minuteLimit);
            $response->headers->set('X-RateLimit-Remaining-Minute', (string) $minuteRemaining);
            $response->headers->set('X-RateLimit-Limit-Hour', (string) $hourLimit);
            $response->headers->set('X-RateLimit-Remaining-Hour', (string) $hourRemaining);
        }

        return $response;
    }
}
