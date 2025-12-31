<?php

namespace App\Contracts\V1;

use Illuminate\Http\UploadedFile;

interface DocumentServiceInterface
{
    public function uploadDocument(
        int $companyId,
        int $userId,
        UploadedFile $file,
        array $metadata = []
    ): array;

    public function listDocuments(int $companyId, array $filters = []): array;

    public function getDocument(int $companyId, string $documentUuid): array;

    public function deleteDocument(int $companyId, string $documentUuid): bool;
}
