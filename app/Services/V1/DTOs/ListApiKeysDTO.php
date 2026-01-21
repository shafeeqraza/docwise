<?php

namespace App\Services\V1\DTOs;

/**
 * Data Transfer Object for listing API keys with filters.
 */
readonly class ListApiKeysDTO
{
    public function __construct(
        public int $companyId,
        public ?bool $isActive = null,
        public ?string $search = null,
        public int $perPage = 15
    ) {}
}
