<?php

namespace App\Repositories\V1;

use App\Models\Document;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DocumentRepository
{
    /**
     * Create a new document.
     */
    public function create(array $data): Document
    {
        return Document::create($data);
    }

    /**
     * Find document by UUID and company ID.
     */
    public function findByUuidAndCompany(string $uuid, int $companyId): ?Document
    {
        return Document::where('company_id', $companyId)
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * Find document by UUID and company ID or fail.
     */
    public function findByUuidAndCompanyOrFail(string $uuid, int $companyId): Document
    {
        return Document::where('company_id', $companyId)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * Get documents by company with filters.
     */
    public function getByCompany(int $companyId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Document::where('company_id', $companyId)
            ->with(['uploadedBy:id,name,email', 'versions']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['file_type'])) {
            $query->where('file_type', $filters['file_type']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Soft delete document.
     */
    public function delete(Document $document): bool
    {
        return $document->delete();
    }
}
