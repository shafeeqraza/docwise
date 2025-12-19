<?php

namespace App\Http\Middleware;

use App\Services\V1\Auth\AuthCookieService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWithCookie
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
        // Check if Authorization header is already set
        if ($request->bearerToken()) {
            return $next($request);
        }

        // If no Authorization header, check for token in cookie
        $cookieService = app(AuthCookieService::class);
        $token = $request->cookie($cookieService->getCookieName());

        if ($token) {
            // Set Authorization header from cookie so Sanctum can authenticate
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        return $next($request);
    }
}
