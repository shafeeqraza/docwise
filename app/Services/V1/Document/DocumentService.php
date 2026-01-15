<?php

namespace App\Services\V1\Document;

use App\Services\V1\Contracts\DocumentServiceInterface;
use App\Domains\RAG\DTOs\DocumentDTO;
use App\Domains\RAG\Pipelines\DocumentIngestionPipeline;
use App\Exceptions\DocumentProcessingException;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\IngestionJob;
use App\Repositories\V1\DocumentRepository;
use App\Services\V1\DTOs\DeleteDocumentDTO;
use App\Services\V1\DTOs\GetDocumentDTO;
use App\Services\V1\DTOs\ListDocumentsDTO;
use App\Services\V1\DTOs\UploadDocumentDTO;
use App\Domains\RAG\Services\DocumentProcessingStatusService;
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
        private DocumentProcessingStatusService $statusService,
        private DocumentIngestionPipeline $pipeline,

    ) {}

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

            // Create document record
            $document = $this->documentRepository->create([
                'company_id' => $dto->companyId,
                'title' => $dto->title ?? $fileMetadata['original_filename'],
                'description' => $dto->description,
                'source_type' => 'upload',
                'file_type' => $fileMetadata['extension'],
                'status' => 'uploaded',
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

            // Fire event to trigger document processing queue job
            // This is done here (not in observer) because version and ingestion job must exist first
            // The ProcessDocumentUploaded listener will queue the ProcessDocument job
            // event(new DocumentUploaded($document, $version, $ingestionJob));
            $chunksWithEmbeddings = $this->processDocument($document->id, $version->id, $ingestionJob->id);

            $document->load('uploadedBy');

            return new DocumentResource($document);
        });
    }

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

    public function getDocument(GetDocumentDTO $dto): DocumentResource
    {
        $document = $this->documentRepository->findByUuidAndCompanyOrFail($dto->documentUuid, $dto->companyId);
        $document->load(['uploadedBy:id,name,email', 'versions', 'ingestionJobs']);

        return new DocumentResource($document);
    }

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

    public function processDocument(int $documentId, int $versionId, int $ingestionJobId)
    {
        $document = Document::findOrFail($documentId);
        $version = DocumentVersion::findOrFail($versionId);
        $ingestionJob = IngestionJob::findOrFail($ingestionJobId);
        // Mark as processing
        $this->statusService->markAsProcessing($document, $version, $ingestionJob);

        // Convert Document to DocumentDTO
        $documentDTO = new DocumentDTO(
            id: $document->id,
            title: $document->title,
            content: $document->file_url, // File URL for loader
            fileType: $document->file_type,
            metadata: $document->metadata ?? []
        );

        // Get configuration from company
        $company = $document->company;
        $embeddingModel = $company->getEmbeddingModel();
        $chunkSize = $company->getChunkSize();

        // Process document through pipeline (handles: persist chunks, generate embeddings, store vectors, update chunks and version)
        $chunkDTOs = $this->pipeline->process($documentDTO, [
            'embedding_model' => $embeddingModel,
            'chunk_size' => $chunkSize,
            'document_id' => $document->id,
            'version_id' => $version->id,
            'company_id' => $document->company_id,
            'on_complete' => function (int $chunkCount, ?string $model) use ($version, $embeddingModel) {
                // Update version with chunk count and embedding model
                $version->update([
                    'chunk_count' => $chunkCount,
                    'embedding_model' => $model ?? $embeddingModel,
                ]);
            },
        ]);

        if (empty($chunkDTOs)) {
            throw new DocumentProcessingException('No chunks created from document');
        }

        // Mark as completed
        $this->statusService->markAsCompleted($document, $version, $ingestionJob);
        return $chunkDTOs;
    }
}
