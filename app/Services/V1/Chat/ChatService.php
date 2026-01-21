<?php

namespace App\Services\V1\Chat;

use App\Domains\RAG\Pipelines\ChatRAGPipeline;
use App\Http\Resources\ChatMessageResource;
use App\Http\Resources\ChatSessionResource;
use App\Models\ChatSession;
use App\Models\Feedback;
use App\Repositories\V1\Contracts\ChatRepositoryInterface;
use App\Repositories\V1\Contracts\RetrievedChunkRepositoryInterface;
use App\Services\V1\Contracts\ChatServiceInterface;
use App\Services\V1\DTOs\GetChatMessagesDTO;
use App\Services\V1\DTOs\SendChatMessageDTO;
use App\Services\V1\DTOs\SubmitFeedbackDTO;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

/**
 * Service for handling chat interactions.
 *
 * Follows Single Responsibility Principle (SRP): Chat session/message management only.
 * RAG pipeline logic extracted to ChatRAGPipeline.
 * Follows Dependency Inversion Principle (DIP): Depends on interfaces, not implementations.
 */
class ChatService implements ChatServiceInterface
{
    public function __construct(
        private readonly ChatRepositoryInterface $chatRepository,
        private readonly RetrievedChunkRepositoryInterface $retrievedChunkRepository,
        private readonly ChatRAGPipeline $ragPipeline
    ) {}

    /**
     * Send a chat message and generate AI response.
     *
     * @param SendChatMessageDTO $dto
     * @return ChatSessionResource
     */
    public function sendMessage(SendChatMessageDTO $dto): ChatSessionResource
    {
        // Get or create session
        $session = $this->getOrCreateSession($dto);

        // Create user message
        $this->chatRepository->createMessage([
            'session_id' => $session->id,
            'role' => 'user',
            'content' => $dto->message,
            'created_at' => now(),
        ]);

        // Execute RAG pipeline
        $response = $this->executeRAGPipeline($session, $dto);

        // Create assistant message
        $assistantMessage = $this->chatRepository->createMessage([
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => $response['content'],
            'tokens_prompt' => $response['tokens_prompt'],
            'tokens_completion' => $response['tokens_completion'],
            'model_used' => $response['model'],
            'confidence_score' => $response['confidence_score'],
            'citations' => $response['citations'],
            'created_at' => now(),
        ]);

        // Store retrieved chunks
        $this->storeRetrievedChunks($assistantMessage->id, $response['retrieved_chunks']);

        // Update session statistics
        $this->updateSessionStatistics($session, $response);

        // Refresh session and load latest message
        $session->refresh();
        $session->setRelation('latestMessage', $assistantMessage);

        return new ChatSessionResource($session);
    }

    /**
     * Get chat messages for a session.
     *
     * @param GetChatMessagesDTO $dto
     * @return AnonymousResourceCollection
     */
    public function getMessages(GetChatMessagesDTO $dto): AnonymousResourceCollection
    {
        $session = $this->chatRepository->findSessionByUuid($dto->sessionUuid, $dto->companyId);

        if (!$session) {
            throw new ModelNotFoundException('Chat session not found');
        }

        $messages = $this->chatRepository->getMessagesForSession($session->id);

        return ChatMessageResource::collection($messages);
    }

    /**
     * Submit feedback for a message.
     *
     * @param SubmitFeedbackDTO $dto
     * @return array
     */
    public function submitFeedback(SubmitFeedbackDTO $dto): array
    {
        $message = $this->chatRepository->findMessageById($dto->messageId);

        if (!$message) {
            throw new ModelNotFoundException('Chat message not found');
        }

        // Check if feedback already exists
        $existingFeedback = Feedback::where('message_id', $message->id)->first();

        if ($existingFeedback) {
            // Update existing feedback
            $existingFeedback->update([
                'feedback_type' => $dto->type,
                'rating' => $dto->rating,
                'comment' => $dto->comment,
                'updated_at' => now(),
            ]);
        } else {
            // Create new feedback
            Feedback::create([
                'message_id' => $message->id,
                'session_id' => $message->session_id,
                'feedback_type' => $dto->type,
                'rating' => $dto->rating,
                'comment' => $dto->comment,
                'created_at' => now(),
            ]);
        }

        return [
            'success' => true,
            'message' => 'Feedback submitted successfully',
        ];
    }

    /**
     * Execute RAG pipeline and return response.
     *
     * @param ChatSession $session
     * @param SendChatMessageDTO $dto
     * @return array
     */
    private function executeRAGPipeline(ChatSession $session, SendChatMessageDTO $dto): array
    {
        // Get configuration
        $embeddingModel = $dto->embeddingModel ?? config('chat.default_embedding_model', 'models/gemini-embedding-001');
        $llmModel = $dto->llmModel ?? config('chat.default_llm_model', 'Gemini 2.5 Flash');
        $maxContextChunks = $dto->maxContextChunks ?? config('chat.max_context_chunks', 5);
        $maxHistory = $dto->maxChatHistory ?? config('chat.max_chat_history', 10);
        $temperature = $dto->temperature ?? config('chat.temperature', 0.7);
        $maxOutputTokens = $dto->maxTokens ?? config('chat.max_output_tokens', 1000);
        $maxTotalTokens = config('chat.max_total_tokens', 8000);

        // Get chat history
        $chatHistory = $this->getChatHistory($session, $maxHistory);

        // Execute pipeline
        return $this->ragPipeline->execute([
            'message' => $dto->message,
            'company_id' => $session->company_id,
            'chat_history' => $chatHistory,
            'embedding_model' => $embeddingModel,
            'llm_model' => $llmModel,
            'max_context_chunks' => $maxContextChunks,
            'temperature' => $temperature,
            'max_output_tokens' => $maxOutputTokens,
            'max_total_tokens' => $maxTotalTokens,
        ]);
    }

    /**
     * Store retrieved chunks for a message.
     *
     * @param int $messageId
     * @param array $chunks
     * @return void
     */
    private function storeRetrievedChunks(int $messageId, array $chunks): void
    {
        if (empty($chunks)) {
            return;
        }

        foreach ($chunks as $index => $chunkDTO) {
            $this->retrievedChunkRepository->create([
                'message_id' => $messageId,
                'chunk_id' => $chunkDTO->id,
                'document_id' => $chunkDTO->documentId,
                'similarity_score' => $chunkDTO->metadata['similarity_score'] ?? 0.0,
                'rank_position' => $index + 1,
                'used_in_context' => true,
                'metadata' => $chunkDTO->metadata,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Update session statistics after message generation.
     *
     * @param ChatSession $session
     * @param array $response
     * @return void
     */
    private function updateSessionStatistics(ChatSession $session, array $response): void
    {
        $this->chatRepository->updateSession($session, [
            'message_count' => $session->message_count + 2, // User + assistant
            'total_tokens' => $session->total_tokens + $response['tokens_prompt'] + $response['tokens_completion'],
        ]);
    }

    /**
     * Get or create chat session.
     *
     * @param SendChatMessageDTO $dto
     * @return ChatSession
     */
    private function getOrCreateSession(SendChatMessageDTO $dto): ChatSession
    {
        if ($dto->sessionId) {
            $session = $this->chatRepository->findSessionByUuid($dto->sessionId, $dto->companyId);
            if ($session) {
                return $session;
            }
        }

        // Create new session
        return $this->chatRepository->createSession([
            'company_id' => $dto->companyId,
            'session_identifier' => (string) Str::uuid(),
            'user_metadata' => $dto->userMetadata,
            'created_at' => now(),
        ]);
    }

    /**
     * Get chat history for context.
     *
     * @param ChatSession $session
     * @param int $maxHistory
     * @return array
     */
    private function getChatHistory(ChatSession $session, int $maxHistory): array
    {
        if ($maxHistory <= 0) {
            return [];
        }

        $messages = $this->chatRepository->getMessagesForSession($session->id, $maxHistory);

        $history = [];
        foreach ($messages as $message) {
            $history[] = [
                'role' => $message->role,
                'content' => $message->content,
            ];
        }

        return $history;
    }
}
