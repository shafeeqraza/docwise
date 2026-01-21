<?php

namespace App\Services\V1\Document\Upload;

use App\Models\Document;
use App\Exceptions\DocumentValidationException;

class DocumentValidationService
{
    /**
     * Check for duplicate document by checksum.
     * File type and size validation is handled in UploadDocumentRequest.
     */
    public function checkDuplicate(int $companyId, string $checksum): void
    {
        $existing = Document::where('company_id', $companyId)
            ->where('checksum', $checksum)
            ->first();

        if ($existing) {
            throw new DocumentValidationException('Duplicate document detected (same file already uploaded)');
        }
    }
}
