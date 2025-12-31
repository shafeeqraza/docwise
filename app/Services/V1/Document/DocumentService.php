<?php

namespace App\Services\V1\Document;

use App\Contracts\V1\DocumentServiceInterface;
use App\Http\Resources\DocumentResource;
use App\Repositories\V1\DocumentRepository;
use App\Services\V1\Common\PaginatedResponseFormatter;
use App\Services\V1\Document\Upload\DocumentValidationService;
use App\Services\V1\Document\Upload\FileStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class DocumentService implements DocumentServiceInterface
{
    public function __construct(
        private DocumentRepository $documentRepository,
        private FileStorageService $fileStorageService,
        private DocumentValidationService $validationService,
        private DocumentVersionService $versionService,
        private IngestionJobService $ingestionJobService
    ) {}

    public function uploadDocument(
        int $companyId,
        int $userId,
        UploadedFile $file,
        array $metadata = []
    ): array {
        return DB::transaction(function () use ($companyId, $userId, $file, $metadata) {
            // Calculate checksum from uploaded file (before storage)
            $checksum = $this->fileStorageService->calculateChecksumFromFile($file);

            // Check for duplicate before storing
            $this->validationService->checkDuplicate($companyId, $checksum);

            // Store file only if not a duplicate
            $storageResult = $this->fileStorageService->storeFile($companyId, $file);

            // Get file metadata
            $fileMetadata = $this->fileStorageService->getFileMetadata($file);

            // Create document record
            $document = $this->documentRepository->create([
                'company_id' => $companyId,
                'title' => $metadata['title'] ?? $fileMetadata['original_filename'],
                'description' => $metadata['description'] ?? null,
                'source_type' => 'upload',
                'file_type' => $fileMetadata['extension'],
                'status' => 'uploaded',
                'uploaded_by' => $userId,
                'public_id' => $storageResult['public_id'],
                'file_url' => $storageResult['url'],
                'original_filename' => $fileMetadata['original_filename'],
                'mime_type' => $fileMetadata['mime_type'],
                'file_size' => $fileMetadata['file_size'],
                'checksum' => $checksum,
                'language' => $metadata['language'] ?? 'en',
                'tags' => $metadata['tags'] ?? [],
                'metadata' => $metadata['metadata'] ?? [],
            ]);

            // Create initial version
            $version = $this->versionService->createInitialVersion($document);

            // Create ingestion job record
            $ingestionJob = $this->ingestionJobService->createProcessingJob($document);

            // Process document queue is fired via Document model's created event

            return [
                'document' => $document->load('uploadedBy'),
                'version' => $version,
                'ingestion_job' => $ingestionJob,
            ];
        });
    }

    public function listDocuments(int $companyId, array $filters = []): array
    {
        $perPage = $filters['per_page'] ?? 20;
        $documents = $this->documentRepository->getByCompany($companyId, $filters, $perPage);

        return PaginatedResponseFormatter::formatWithResource($documents, DocumentResource::class);
    }

    public function getDocument(int $companyId, string $documentUuid): array
    {
        $document = $this->documentRepository->findByUuidAndCompanyOrFail($documentUuid, $companyId);
        $document->load(['uploadedBy:id,name,email', 'versions', 'ingestionJobs']);

        return ['data' => new DocumentResource($document)];
    }

    public function deleteDocument(int $companyId, string $documentUuid): bool
    {
        $document = $this->documentRepository->findByUuidAndCompanyOrFail($documentUuid, $companyId);

        // Delete file from Cloudinary
        if ($document->public_id) {
            $this->fileStorageService->deleteFile($document->public_id);
        }

        $this->versionService->deleteByDocumentId($document->id);

        $this->ingestionJobService->deleteByDocumentId($document->id);

        $this->documentRepository->delete($document);

        // TODO: Queue cleanup job for Qdrant vectors (file storage cleanup is handled above)

        return true;
    }
}
