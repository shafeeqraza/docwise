<?php

namespace App\Services\V1\DTOs;

/**
 * Data Transfer Object for getting a single document.
 */
readonly class GetDocumentDTO
{
    public function __construct(
        public int $companyId,
        public string $documentUuid
    ) {}
}
