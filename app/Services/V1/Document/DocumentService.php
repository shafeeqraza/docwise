<?php

namespace App\Services\V1\Document;

use App\Enums\DocumentFileType;
use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use App\Events\DocumentUploaded;
use App\Exceptions\DocumentValidationException;
use App\Services\V1\Contracts\DocumentServiceInterface;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\Repositories\V1\DocumentRepository;
use App\Services\V1\DTOs\DeleteDocumentDTO;
use App\Services\V1\DTOs\GetDocumentDTO;
use App\Services\V1\DTOs\ListDocumentsDTO;
use App\Services\V1\DTOs\UploadDocumentDTO;
use App\Services\V1\Document\Upload\DocumentValidationService;
use App\Services\V1\Document\Upload\FileStorageService;
use Illuminate\Support\Facades\DB;

class DocumentService implements DocumentServiceInterface
{
    public function __construct(
        private DocumentRepository $documentRepository,
        private FileStorageService $fileStorageService,
        private DocumentValidationService $validationService,
        private DocumentVersionService $versionService,
        private IngestionJobService $ingestionJobService,

    ) {}

    #[\Override]
    public function uploadDocument(UploadDocumentDTO $dto): DocumentResource
    {
        return DB::transaction(function () use ($dto) {
            // Calculate checksum from uploaded file (before storage)
            $checksum = $this->fileStorageService->calculateChecksumFromFile($dto->file);

            // Check for duplicate before storing
            $this->validationService->checkDuplicate($dto->companyId, $checksum);

            // Store file only if not a duplicate
            $storageResult = $this->fileStorageService->storeFile($dto->companyId, $dto->file);

            // Get file metadata
            $fileMetadata = $this->fileStorageService->getFileMetadata($dto->file);

            // The uploaded extension is user-controlled, so it may still fall outside
            // the file types the schema accepts even after request validation.
            $fileType = DocumentFileType::tryFrom($fileMetadata['extension'])
                ?? throw new DocumentValidationException(
                    "Unsupported file type: {$fileMetadata['extension']}"
                );

            // Create document record
            $document = $this->documentRepository->create([
                'company_id' => $dto->companyId,
                'title' => $dto->title ?? $fileMetadata['original_filename'],
                'description' => $dto->description,
                'source_type' => DocumentSourceType::UPLOAD,
                'file_type' => $fileType,
                'status' => DocumentStatus::UPLOADED,
                'uploaded_by' => $dto->userId,
                'public_id' => $storageResult['public_id'],
                'file_url' => $storageResult['secure_url'],
                'original_filename' => $fileMetadata['original_filename'],
                'mime_type' => $fileMetadata['mime_type'],
                'file_size' => $fileMetadata['file_size'],
                'checksum' => $checksum,
                'language' => $dto->language,
                'tags' => $dto->tags,
                'metadata' => $dto->metadata,
            ]);

            // Create initial version
            $version = $this->versionService->createInitialVersion($document);

            // Create ingestion job record
            $ingestionJob = $this->ingestionJobService->createProcessingJob($document);

            event(new DocumentUploaded($document, $version, $ingestionJob));

            $document->load('uploadedBy');

            return new DocumentResource($document);
        });
    }

    #[\Override]
    public function listDocuments(ListDocumentsDTO $dto): PaginatedResourceCollection
    {
        $filters = array_filter([
            'status' => $dto->status,
            'file_type' => $dto->fileType,
            'search' => $dto->search,
        ], fn($value) => $value !== null);

        $documents = $this->documentRepository->getByCompany($dto->companyId, $filters, $dto->perPage);

        return new PaginatedResourceCollection(
            DocumentResource::collection($documents->items()),
            $documents
        );
    }

    #[\Override]
    public function getDocument(GetDocumentDTO $dto): DocumentResource
    {
        $document = $this->documentRepository->findByUuidAndCompanyOrFail($dto->documentUuid, $dto->companyId);
        $document->load(['uploadedBy:id,name,email', 'versions', 'ingestionJobs']);

        return new DocumentResource($document);
    }

    #[\Override]
    public function deleteDocument(DeleteDocumentDTO $dto): bool
    {
        $document = $this->documentRepository->findByUuidAndCompanyOrFail($dto->documentUuid, $dto->companyId);

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
