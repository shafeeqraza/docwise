<?php

namespace App\Repositories\V1;

use App\Models\Document;
use App\Models\IngestionJob;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class IngestionJobRepository
{
    /**
     * Create a new ingestion job.
     */
    public function create(array $data): IngestionJob
    {
        return IngestionJob::create($data);
    }

    /**
     * Find job by ID.
     */
    public function findById(int $id): ?IngestionJob
    {
        return IngestionJob::find($id);
    }

    /**
     * Find job by ID or fail.
     */
    public function findByIdOrFail(int $id): IngestionJob
    {
        return IngestionJob::findOrFail($id);
    }

    /**
     * Find job by UUID.
     */
    public function findByUuid(string $uuid): ?IngestionJob
    {
        return IngestionJob::where('uuid', $uuid)->first();
    }

    /**
     * Find job by UUID or fail.
     */
    public function findByUuidOrFail(string $uuid): IngestionJob
    {
        return IngestionJob::where('uuid', $uuid)->firstOrFail();
    }

    /**
     * Get latest job for a document.
     */
    public function getLatestByDocument(Document $document): ?IngestionJob
    {
        return $document->ingestionJobs()
            ->latest('created_at')
            ->first();
    }

    /**
     * Get all jobs for a document.
     */
    public function getByDocument(Document $document, array $filters = []): Collection
    {
        $query = $document->ingestionJobs();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['job_type'])) {
            $query->where('job_type', $filters['job_type']);
        }

        return $query->latest('created_at')->get();
    }

    /**
     * Get jobs by company with filters.
     */
    public function getByCompany(int $companyId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = IngestionJob::where('company_id', $companyId)
            ->with(['document:id,uuid,title']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['job_type'])) {
            $query->where('job_type', $filters['job_type']);
        }

        if (isset($filters['document_id'])) {
            $query->where('document_id', $filters['document_id']);
        }

        return $query->latest('created_at')->paginate($perPage);
    }

    /**
     * Update job.
     */
    public function update(IngestionJob $job, array $data): bool
    {
        return $job->update($data);
    }

    /**
     * Delete job.
     */
    public function delete(IngestionJob $job): bool
    {
        return $job->delete();
    }

    /**
     * Delete all jobs for a document.
     */
    public function deleteByDocumentId(int $documentId): bool
    {
        return IngestionJob::where('document_id', $documentId)->delete();
    }
}
