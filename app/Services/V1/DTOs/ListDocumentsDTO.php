<?php

namespace App\Services\V1\DTOs;

use App\Enums\DocumentFileType;
use App\Enums\DocumentStatus;

/**
 * Data Transfer Object for listing documents with filters.
 */
readonly class ListDocumentsDTO
{
    public function __construct(
        public int $companyId,
        public ?DocumentStatus $status = null,
        public ?DocumentFileType $fileType = null,
        public ?string $search = null,
        public int $perPage = 20
    ) {}
}
