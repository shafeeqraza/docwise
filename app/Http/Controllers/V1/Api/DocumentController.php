<?php

namespace App\Http\Controllers\V1\Api;

use App\Contracts\V1\DocumentServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\Concerns\ResponseHandler;
use App\Http\Requests\ListDocumentsRequest;
use App\Http\Requests\UploadDocumentRequest;
use App\Repositories\V1\AdminActionRepositoryInterface;
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
        $companyId = $request->attributes->get('current_company_id');

        $user = $request->user();
        $userId = $user->id;

        $metadata = [
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'tags' => $request->input('tags', []),
            'language' => $request->input('language', 'en'),
        ];

        $result = $this->documentService->uploadDocument($companyId, $userId, $request->file('file'), $metadata);

        // Log admin action
        $this->adminActionRepository->logAction(
            user: $user,
            action: 'document.upload',
            targetCompanyId: $companyId,
            details: [
                'document_title' => $metadata['title'] ?? $request->file('file')->getClientOriginalName(),
                'file_type' => $request->file('file')->getClientOriginalExtension(),
                'file_size' => $request->file('file')->getSize(),
            ],
            request: $request
        );

        return $this->respondSuccess(
            $result,
            'Document uploaded successfully',
            201
        );
    }

    public function index(ListDocumentsRequest $request): JsonResponse
    {
        $companyId = $request->attributes->get('current_company_id');

        $filters = [
            'status' => $request->query('status'),
            'file_type' => $request->query('file_type'),
            'search' => $request->query('search'),
            'per_page' => $request->query('per_page', 20),
        ];

        $result = $this->documentService->listDocuments($companyId, $filters);

        // Log admin action
        $this->adminActionRepository->logAction(
            user: $request->user(),
            action: 'document.list',
            targetCompanyId: $companyId,
            details: [
                'filters' => $filters,
                'result_count' => count($result['data'] ?? []),
            ],
            request: $request
        );

        return $this->respondSuccess($result);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $companyId = $request->attributes->get('current_company_id');

        $result = $this->documentService->getDocument($companyId, $uuid);

        // Log admin action
        $this->adminActionRepository->logAction(
            user: $request->user(),
            action: 'document.view',
            targetCompanyId: $companyId,
            details: [
                'document_uuid' => $uuid,
                'document_id' => $result['data']->id ?? null,
                'document_title' => $result['data']->title ?? null,
            ],
            request: $request
        );

        return $this->respondSuccess($result['data']);
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $companyId = $request->attributes->get('current_company_id');

        // Get document info before deletion for logging
        $document = null;
        try {
            $documentResult = $this->documentService->getDocument($companyId, $uuid);
            $document = $documentResult['data'] ?? null;
        } catch (\Exception $e) {
            // Document might not exist, continue with deletion attempt
        }

        $this->documentService->deleteDocument($companyId, $uuid);

        // Log admin action
        $this->adminActionRepository->logAction(
            user: $request->user(),
            action: 'document.delete',
            targetCompanyId: $companyId,
            details: [
                'document_uuid' => $uuid,
                'document_id' => $document->id ?? null,
                'document_title' => $document->title ?? null,
            ],
            request: $request
        );

        return $this->respondMessage('Document deleted successfully');
    }
}
