<?php

namespace App\Services\V1\DTOs;

/**
 * Data Transfer Object for deleting a document.
 */
final readonly class DeleteDocumentDTO
{
    public function __construct(
        public int $companyId,
        public string $documentUuid
    ) {}
}
