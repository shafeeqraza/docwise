<?php

namespace App\Services\V1\DTOs;

/**
 * Data Transfer Object for getting an API key.
 */
readonly class GetApiKeyDTO
{
    public function __construct(
        public int $companyId,
        public string|int $identifier
    ) {}
}
