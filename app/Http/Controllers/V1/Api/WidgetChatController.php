<?php

namespace App\Http\Controllers\V1\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\Concerns\ResponseHandler;
use App\Http\Requests\SendChatMessageRequest;
use App\Http\Requests\SubmitFeedbackRequest;
use App\Services\V1\Contracts\ApiKeyUsageServiceInterface;
use App\Services\V1\Contracts\ChatServiceInterface;
use App\Services\V1\DTOs\GetChatMessagesDTO;
use App\Services\V1\DTOs\SendChatMessageDTO;
use App\Services\V1\DTOs\SubmitFeedbackDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WidgetChatController extends Controller
{
    use ResponseHandler;

    public function __construct(
        private readonly ChatServiceInterface $chatService,
        private readonly ApiKeyUsageServiceInterface $usageService
    ) {}

    /**
     * Send a chat message and get AI response.
     *
     * @param SendChatMessageRequest $request
     * @return JsonResponse
     */
    public function chat(SendChatMessageRequest $request): JsonResponse
    {
        $startTime = microtime(true);
        $apiKey = $request->attributes->get('api_key');
        $companyId = $request->attributes->get('current_company_id');

        try {
            // Create DTO from validated request
            $validated = $request->validated();
            $dto = new SendChatMessageDTO(
                companyId: $companyId,
                message: $validated['message'],
                sessionId: $validated['session_id'] ?? null,
                userMetadata: $validated['user_metadata'] ?? null,
            );

            // Send message via service
            $resource = $this->chatService->sendMessage($dto);

            // Log usage
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);
            $sessionData = $resource->resource;
            $latestMessage = $sessionData->latestMessage ?? null;

            $this->usageService->logRequest($apiKey, [
                'endpoint' => '/api/widget/chat',
                'method' => 'POST',
                'session_id' => $sessionData->uuid,
                'tokens_prompt' => $latestMessage?->tokens_prompt ?? 0,
                'tokens_completion' => $latestMessage?->tokens_completion ?? 0,
                'latency_ms' => $latencyMs,
                'status_code' => 200,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->respondResource($resource, 'Message sent successfully');
        } catch (\Exception $e) {
            Log::error('Widget chat error', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            // Log failed request
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);
            $validated = $request->validated();
            $this->usageService->logRequest($apiKey, [
                'endpoint' => '/api/widget/chat',
                'method' => 'POST',
                'session_id' => $validated['session_id'] ?? null,
                'tokens_prompt' => 0,
                'tokens_completion' => 0,
                'latency_ms' => $latencyMs,
                'status_code' => 500,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => ['error' => $e->getMessage()],
            ]);

            return $this->respondError(
                'An error occurred while processing your request. Please try again.',
                500
            );
        }
    }

    /**
     * Get chat history for a session.
     *
     * @param Request $request
     * @param string $session UUID of the session
     * @return JsonResponse
     */
    public function getMessages(Request $request, string $session): JsonResponse
    {
        $companyId = $request->attributes->get('current_company_id');

        try {
            $dto = new GetChatMessagesDTO(
                companyId: $companyId,
                sessionUuid: $session
            );

            $messages = $this->chatService->getMessages($dto);

            return $this->respondResource($messages, 'Messages retrieved successfully');
        } catch (\Exception $e) {
            return $this->respondError(
                $e->getMessage(),
                404
            );
        }
    }

    /**
     * Submit feedback for a message.
     *
     * @param SubmitFeedbackRequest $request
     * @param string $session UUID of the session
     * @return JsonResponse
     */
    public function submitFeedback(SubmitFeedbackRequest $request, string $session): JsonResponse
    {
        $companyId = $request->attributes->get('current_company_id');

        try {
            // Create DTO from validated request
            $validated = $request->validated();

            $dto = new SubmitFeedbackDTO(
                companyId: $companyId,
                sessionUuid: $session,
                messageId: $validated['message_id'],
                type: $validated['type'],
                rating: $validated['rating'] ?? null,
                comment: $validated['comment'] ?? null
            );

            $this->chatService->submitFeedback($dto);

            return $this->respondMessage('Feedback submitted successfully');
        } catch (\Exception $e) {
            return $this->respondError(
                $e->getMessage(),
                404
            );
        }
    }
}
