<?php

namespace App\Services\V1\Auth;

use Illuminate\Support\Facades\RateLimiter;

class LoginAttemptService
{
    private const MAX_ATTEMPTS = 3;
    private const LOCKOUT_DURATION = 60; // seconds (1 minute)

    /**
     * Get rate limiter key for login attempts.
     *
     * @param string $email
     * @param string $ipAddress
     * @return string
     */
    private function getRateLimiterKey(string $email, string $ipAddress): string
    {
        return "superadmin_login:{$email}:{$ipAddress}";
    }

    /**
     * Check if login attempts exceeded limit.
     *
     * @param string $email
     * @param string $ipAddress
     * @return bool
     */
    public function hasExceededLimit(string $email, string $ipAddress): bool
    {
        $key = $this->getRateLimiterKey($email, $ipAddress);
        return RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS);
    }

    /**
     * Get remaining attempts.
     *
     * @param string $email
     * @param string $ipAddress
     * @return int
     */
    public function getRemainingAttempts(string $email, string $ipAddress): int
    {
        $key = $this->getRateLimiterKey($email, $ipAddress);
        $attempts = RateLimiter::attempts($key);
        return max(0, self::MAX_ATTEMPTS - $attempts);
    }

    /**
     * Get current attempt count.
     *
     * @param string $email
     * @param string $ipAddress
     * @return int
     */
    public function getAttempts(string $email, string $ipAddress): int
    {
        $key = $this->getRateLimiterKey($email, $ipAddress);
        return RateLimiter::attempts($key);
    }

    /**
     * Increment login attempts.
     *
     * @param string $email
     * @param string $ipAddress
     * @return void
     */
    public function incrementAttempts(string $email, string $ipAddress): void
    {
        $key = $this->getRateLimiterKey($email, $ipAddress);
        RateLimiter::hit($key, self::LOCKOUT_DURATION);
    }

    /**
     * Reset login attempts (on successful login).
     *
     * @param string $email
     * @param string $ipAddress
     * @return void
     */
    public function resetAttempts(string $email, string $ipAddress): void
    {
        $key = $this->getRateLimiterKey($email, $ipAddress);
        RateLimiter::clear($key);
    }

    /**
     * Get lockout expiration time in seconds.
     *
     * @param string $email
     * @param string $ipAddress
     * @return int|null
     */
    public function getLockoutExpirationSeconds(string $email, string $ipAddress): ?int
    {
        if (!$this->hasExceededLimit($email, $ipAddress)) {
            return null;
        }

        $key = $this->getRateLimiterKey($email, $ipAddress);
        return RateLimiter::availableIn($key);
    }

    /**
     * Get lockout expiration time in minutes.
     *
     * @param string $email
     * @param string $ipAddress
     * @return int|null
     */
    public function getLockoutExpirationMinutes(string $email, string $ipAddress): ?int
    {
        $seconds = $this->getLockoutExpirationSeconds($email, $ipAddress);
        return $seconds ? (int) ceil($seconds / 60) : null;
    }
}
