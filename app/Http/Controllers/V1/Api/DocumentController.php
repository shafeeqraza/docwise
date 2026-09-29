<?php

namespace App\Http\Controllers\V1\Api;

use App\Enums\DocumentFileType;
use App\Enums\DocumentStatus;
use App\Services\V1\Contracts\DocumentServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\Concerns\ResponseHandler;
use App\Http\Requests\ListDocumentsRequest;
use App\Http\Requests\UploadDocumentRequest;
use App\Models\Document;
use App\Repositories\V1\Contracts\AdminActionRepositoryInterface;
use App\Services\V1\DTOs\DeleteDocumentDTO;
use App\Services\V1\DTOs\GetDocumentDTO;
use App\Services\V1\DTOs\ListDocumentsDTO;
use App\Services\V1\DTOs\UploadDocumentDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    use ResponseHandler;

    public function __construct(
        private DocumentServiceInterface $documentService,
        private AdminActionRepositoryInterface $adminActionRepository
    ) {}

    /**
     * Upload a document.
     *
     * Company context is resolved from:
     * - X-Company-Id header (for superadmin impersonation)
     * - User's company_id (for regular users)
     */
    public function upload(UploadDocumentRequest $request): JsonResponse
    {
        $this->authorize('create', Document::class);

        $companyId = $request->attributes->get('current_company_id');
        $user = $request->user();

        $dto = new UploadDocumentDTO(
            companyId: $companyId,
            userId: $user->id,
            file: $request->file('file'),
            title: $request->input('title'),
            description: $request->input('description'),
            tags: $request->input('tags', []),
            language: $request->input('language', 'en'),
            metadata: []
        );

        $resource = $this->documentService->uploadDocument($dto);

        // Log admin action
        $this->adminActionRepository->logAction(
            user: $user,
            action: 'document.upload',
            targetCompanyId: $companyId,
            details: [
                'document_title' => $dto->title ?? $request->file('file')->getClientOriginalName(),
                'file_type' => $request->file('file')->getClientOriginalExtension(),
                'file_size' => $request->file('file')->getSize(),
            ],
            request: $request
        );

        return $this->respondResource(
            $resource,
            'Document uploaded successfully',
            201
        );
    }

    public function index(ListDocumentsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Document::class);

        $companyId = $request->attributes->get('current_company_id');

        $dto = new ListDocumentsDTO(
            companyId: $companyId,
            status: DocumentStatus::tryFrom((string) $request->query('status')),
            fileType: DocumentFileType::tryFrom((string) $request->query('file_type')),
            search: $request->query('search'),
            perPage: (int) $request->query('per_page', 20)
        );

        $resource = $this->documentService->listDocuments($dto);

        // Log admin action
        $this->adminActionRepository->logAction(
            user: $request->user(),
            action: 'document.list',
            targetCompanyId: $companyId,
            details: [
                'filters' => [
                    'status' => $dto->status?->value,
                    'file_type' => $dto->fileType?->value,
                    'search' => $dto->search,
                ],
                'result_count' => $resource->collection->count(),
            ],
            request: $request
        );

        return $this->respondResource($resource, 'Documents retrieved successfully');
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $companyId = $request->attributes->get('current_company_id');

        $dto = new GetDocumentDTO(
            companyId: $companyId,
            documentUuid: $uuid
        );

        $resource = $this->documentService->getDocument($dto);
        $this->authorize('view', $resource->resource);

        // Log admin action
        $this->adminActionRepository->logAction(
            user: $request->user(),
            action: 'document.view',
            targetCompanyId: $companyId,
            details: [
                'document_uuid' => $uuid,
                'document_id' => $resource->id ?? null,
                'document_title' => $resource->title ?? null,
            ],
            request: $request
        );

        return $this->respondResource($resource, 'Document retrieved successfully');
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $companyId = $request->attributes->get('current_company_id');

        // Fetch the document first: the policy needs it, and so does the log entry
        $getDto = new GetDocumentDTO(companyId: $companyId, documentUuid: $uuid);
        $document = $this->documentService->getDocument($getDto)->resource;
        $this->authorize('delete', $document);

        $deleteDto = new DeleteDocumentDTO(
            companyId: $companyId,
            documentUuid: $uuid
        );

        $this->documentService->deleteDocument($deleteDto);

        // Log admin action
        $this->adminActionRepository->logAction(
            user: $request->user(),
            action: 'document.delete',
            targetCompanyId: $companyId,
            details: [
                'document_uuid' => $uuid,
                'document_id' => $document->id,
                'document_title' => $document->title,
            ],
            request: $request
        );

        return $this->respondMessage('Document deleted successfully');
    }
}
