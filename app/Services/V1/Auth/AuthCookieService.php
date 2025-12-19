<?php

namespace App\Services\V1\Auth;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;

class AuthCookieService
{
    private const SUPERADMIN_TOKEN_COOKIE = 'superadmin_token';
    private const COOKIE_EXPIRATION_MINUTES = 60 * 24; // 1 day

    /**
     * Create an authentication cookie with token.
     *
     * @param string $token
     * @return Cookie
     */
    public function createAuthCookie(string $token): Cookie
    {
        return cookie(
            name: self::SUPERADMIN_TOKEN_COOKIE,
            value: $token,
            minutes: self::COOKIE_EXPIRATION_MINUTES,
            path: '/',
            domain: config('session.domain'),
            secure: config('session.secure', app()->environment('production')),
            httpOnly: true, // Prevents JavaScript access
            sameSite: config('session.same_site', 'lax')
        );
    }

    /**
     * Create a cookie to clear/expire the authentication cookie.
     *
     * @return Cookie
     */
    public function createExpiredCookie(): Cookie
    {
        return cookie(
            name: self::SUPERADMIN_TOKEN_COOKIE,
            value: '',
            minutes: -2628000, // Expire in the past
            path: '/',
            domain: config('session.domain'),
            secure: config('session.secure', app()->environment('production')),
            httpOnly: true,
            sameSite: config('session.same_site', 'lax')
        );
    }

    /**
     * Attach authentication cookie to response.
     *
     * @param JsonResponse $response
     * @param string $token
     * @return JsonResponse
     */
    public function attachAuthCookie(JsonResponse $response, string $token): JsonResponse
    {
        if (empty($token)) {
            return $response;
        }

        return $response->withCookie($this->createAuthCookie($token));
    }

    /**
     * Attach expired cookie to response to clear authentication.
     *
     * @param JsonResponse $response
     * @return JsonResponse
     */
    public function clearAuthCookie(JsonResponse $response): JsonResponse
    {
        return $response->withCookie($this->createExpiredCookie());
    }

    /**
     * Get the cookie name.
     *
     * @return string
     */
    public function getCookieName(): string
    {
        return self::SUPERADMIN_TOKEN_COOKIE;
    }
}
