<?php

namespace App\Services\V1\DTOs;

/**
 * Data Transfer Object for updating an API key.
 */
readonly class UpdateApiKeyDTO
{
    public function __construct(
        public ?string $name = null,
        public ?int $rateLimitPerMinute = null,
        public ?int $rateLimitPerHour = null,
        public ?bool $isActive = null,
        public ?string $expiresAt = null
    ) {}
}
