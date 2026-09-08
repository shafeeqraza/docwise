<?php

namespace App\Services\V1\Document;

use App\Enums\DocumentVersionProcessingState;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Repositories\V1\DocumentVersionRepository;

class DocumentVersionService
{
    public function __construct(
        private DocumentVersionRepository $repository
    ) {}

    /**
     * Create initial version for a document.
     */
    public function createInitialVersion(Document $document): DocumentVersion
    {
        return $this->repository->create([
            'document_id' => $document->id,
            'version' => 1,
            'processing_state' => DocumentVersionProcessingState::PENDING,
        ]);
    }

    /**
     * Get latest version for a document.
     */
    public function getLatestVersion(Document $document): ?DocumentVersion
    {
        return $this->repository->getLatestByDocument($document);
    }

    /**
     * Get all versions for a document.
     */
    public function getVersions(Document $document): \Illuminate\Database\Eloquent\Collection
    {
        return $this->repository->getByDocument($document);
    }

    /**
     * Get next version number for a document.
     */
    public function getNextVersionNumber(Document $document): int
    {
        return $this->repository->getNextVersionNumber($document);
    }

    /**
     * Update version.
     */
    public function updateVersion(DocumentVersion $version, array $data): bool
    {
        return $this->repository->update($version, $data);
    }

    /**
     * Delete all versions for a document.
     */
    public function deleteByDocumentId(int $documentId): bool
    {
        return $this->repository->deleteByDocumentId($documentId);
    }
}
