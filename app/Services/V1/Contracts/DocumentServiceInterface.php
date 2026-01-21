<?php

namespace App\Services\V1\Contracts;

use App\Http\Resources\DocumentResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\Services\V1\DTOs\DeleteDocumentDTO;
use App\Services\V1\DTOs\GetDocumentDTO;
use App\Services\V1\DTOs\ListDocumentsDTO;
use App\Services\V1\DTOs\UploadDocumentDTO;

interface DocumentServiceInterface
{
    /**
     * Upload a document.
     *
     * @param UploadDocumentDTO $dto
     * @return DocumentResource
     */
    public function uploadDocument(UploadDocumentDTO $dto): DocumentResource;

    /**
     * List documents with filters.
     *
     * @param ListDocumentsDTO $dto
     * @return PaginatedResourceCollection
     */
    public function listDocuments(ListDocumentsDTO $dto): PaginatedResourceCollection;

    /**
     * Get a single document.
     *
     * @param GetDocumentDTO $dto
     * @return DocumentResource
     */
    public function getDocument(GetDocumentDTO $dto): DocumentResource;

    /**
     * Delete a document.
     *
     * @param DeleteDocumentDTO $dto
     * @return bool
     */
    public function deleteDocument(DeleteDocumentDTO $dto): bool;
}
