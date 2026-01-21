<?php

namespace App\Services\V1\DTOs;

/**
 * Data Transfer Object for listing documents with filters.
 */
readonly class ListDocumentsDTO
{
    public function __construct(
        public int $companyId,
        public ?string $status = null,
        public ?string $fileType = null,
        public ?string $search = null,
        public int $perPage = 20
    ) {}
}
