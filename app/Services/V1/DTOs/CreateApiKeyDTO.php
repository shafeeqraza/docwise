<?php

namespace App\Services\V1\DTOs;

/**
 * Data Transfer Object for creating an API key.
 */
final readonly class CreateApiKeyDTO
{
    public function __construct(
        public int $companyId,
        public int $userId,
        public string $name,
        public string $allowedDomain,
        public ?int $rateLimitPerMinute = 60,
        public ?int $rateLimitPerHour = 1000,
        public bool $isActive = true,
        public ?string $expiresAt = null
    ) {}
}
