<?php

namespace App\Repositories\V1;

use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Database\Eloquent\Collection;

class DocumentVersionRepository
{
    /**
     * Create a new document version.
     */
    public function create(array $data): DocumentVersion
    {
        return DocumentVersion::create($data);
    }

    /**
     * Find version by ID.
     */
    public function findById(int $id): ?DocumentVersion
    {
        return DocumentVersion::find($id);
    }

    /**
     * Find version by ID or fail.
     */
    public function findByIdOrFail(int $id): DocumentVersion
    {
        return DocumentVersion::findOrFail($id);
    }

    /**
     * Get latest version for a document.
     */
    public function getLatestByDocument(Document $document): ?DocumentVersion
    {
        return $document->versions()
            ->latest('version')
            ->first();
    }

    /**
     * Get all versions for a document.
     */
    public function getByDocument(Document $document): Collection
    {
        return $document->versions()
            ->orderBy('version', 'desc')
            ->get();
    }

    /**
     * Get next version number for a document.
     */
    public function getNextVersionNumber(Document $document): int
    {
        $latestVersion = $this->getLatestByDocument($document);

        return $latestVersion ? $latestVersion->version + 1 : 1;
    }

    /**
     * Update version.
     */
    public function update(DocumentVersion $version, array $data): bool
    {
        return $version->update($data);
    }

    /**
     * Delete version.
     */
    public function delete(DocumentVersion $version): bool
    {
        return $version->delete();
    }

    /**
     * Delete all versions for a document.
     */
    public function deleteByDocumentId(int $documentId): bool
    {
        return DocumentVersion::where('document_id', $documentId)->delete();
    }
}
