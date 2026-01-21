<?php

namespace App\Services\V1\DTOs;

/**
 * Data Transfer Object for getting a single company.
 */
readonly class GetCompanyDTO
{
    public function __construct(
        public string|int $identifier
    ) {}
}
