<?php

namespace App\Services\V1\DTOs;

use Illuminate\Http\UploadedFile;

/**
 * Data Transfer Object for document upload.
 */
readonly class UploadDocumentDTO
{
    public function __construct(
        public int $companyId,
        public int $userId,
        public UploadedFile $file,
        public ?string $title = null,
        public ?string $description = null,
        public array $tags = [],
        public string $language = 'en',
        public array $metadata = []
    ) {}
}
