<?php

namespace App\Services\V1\DTOs;

/**
 * Data Transfer Object for updating an API key.
 */
final readonly class UpdateApiKeyDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $allowedDomain = null,
        public ?int $rateLimitPerMinute = null,
        public ?int $rateLimitPerHour = null,
        public ?bool $isActive = null,
        public ?string $expiresAt = null
    ) {}
}
