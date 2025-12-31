<?php

namespace App\Services\V1\Document;

use App\Models\Document;
use App\Models\IngestionJob;
use App\Repositories\V1\IngestionJobRepository;

class IngestionJobService
{
    public function __construct(
        private IngestionJobRepository $repository
    ) {}

    /**
     * Create ingestion job for document processing.
     */
    public function createProcessingJob(Document $document): IngestionJob
    {
        return $this->repository->create([
            'company_id' => $document->company_id,
            'document_id' => $document->id,
            'job_type' => 'parse_document', // Valid enum value from migration
            'status' => 'queued',
            'priority' => 0, // Integer as per migration (0 = normal, higher = higher priority)
            'attempts' => 0,
            'max_attempts' => 3,
        ]);
    }

    /**
     * Get latest ingestion job for a document.
     */
    public function getLatestJob(Document $document): ?IngestionJob
    {
        return $this->repository->getLatestByDocument($document);
    }

    /**
     * Get all jobs for a document.
     */
    public function getJobsByDocument(Document $document, array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        return $this->repository->getByDocument($document, $filters);
    }

    /**
     * Get jobs by company with filters.
     */
    public function getJobsByCompany(int $companyId, array $filters = [], int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $this->repository->getByCompany($companyId, $filters, $perPage);
    }

    /**
     * Find job by UUID.
     */
    public function findByUuid(string $uuid): ?IngestionJob
    {
        return $this->repository->findByUuid($uuid);
    }

    /**
     * Update job.
     */
    public function updateJob(IngestionJob $job, array $data): bool
    {
        return $this->repository->update($job, $data);
    }

    /**
     * Delete all jobs for a document.
     */
    public function deleteByDocumentId(int $documentId): bool
    {
        return $this->repository->deleteByDocumentId($documentId);
    }
}
