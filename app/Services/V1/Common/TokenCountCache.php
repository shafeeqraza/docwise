<?php

namespace App\Services\V1\Common;

use Illuminate\Support\Facades\Cache;

/**
 * Cache for token counts to avoid redundant API calls.
 *
 * Follows Single Responsibility Principle (SRP): Token count caching only.
 */
class TokenCountCache
{
    private const CACHE_PREFIX = 'token_count:';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get cached token count or compute and cache it.
     *
     * @param string $text The text to count tokens for
     * @param callable $counter Function to count tokens if not cached
     * @return int Token count
     */
    public function remember(string $text, callable $counter): int
    {
        $cacheKey = $this->getCacheKey($text);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($counter) {
            return $counter();
        });
    }

    /**
     * Generate cache key from text content.
     *
     * @param string $text The text content
     * @return string Cache key
     */
    private function getCacheKey(string $text): string
    {
        // Use hash to keep key size reasonable
        $hash = hash('xxh3', $text);
        return self::CACHE_PREFIX . $hash;
    }

    /**
     * Clear token count cache.
     *
     * @return void
     */
    public function clear(): void
    {
        // This would require tags support in cache driver
        // For now, rely on TTL expiration
    }
}
