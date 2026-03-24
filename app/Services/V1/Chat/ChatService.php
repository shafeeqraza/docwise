<?php

namespace App\Services\V1\Chat;

use App\Http\Resources\ChatMessageResource;
use App\Http\Resources\ChatSessionResource;
use App\Repositories\V1\Contracts\ChatRepositoryInterface;
use App\Repositories\V1\Contracts\FeedbackRepositoryInterface;
use App\Services\V1\Contracts\ChatServiceInterface;
use App\Services\V1\DTOs\GetChatMessagesDTO;
use App\Services\V1\DTOs\SendChatMessageDTO;
use App\Services\V1\DTOs\SubmitFeedbackDTO;
use App\Services\V1\Chat\Exceptions\ChatMessageNotFound;
use App\Services\V1\Chat\Exceptions\ChatSessionNotFound;
use App\Services\V1\Chat\UseCases\SendMessage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Service for handling chat interactions.
 *
 * Follows Single Responsibility Principle (SRP):
 * - Complex operations (RAG pipeline) delegated to specialized use cases
 * - Simple CRUD operations handled directly (no over-engineering)
 * Follows Dependency Inversion Principle (DIP): Depends on interfaces, not implementations.
 */
class ChatService implements ChatServiceInterface
{
    public function __construct(
        private readonly SendMessage $sendMessageUseCase,
        private readonly ChatRepositoryInterface $chatRepository,
        private readonly FeedbackRepositoryInterface $feedbackRepository
    ) {}

    /**
     * Send a chat message and generate AI response.
     * Complex operation - delegated to use case.
     *
     * @param SendChatMessageDTO $dto
     * @return ChatSessionResource
     */
    public function sendMessage(SendChatMessageDTO $dto): ChatSessionResource
    {
        return $this->sendMessageUseCase->execute($dto);
    }

    /**
     * Get chat messages for a session.
     * Simple DB fetch - handled directly.
     *
     * @param GetChatMessagesDTO $dto
     * @return AnonymousResourceCollection
     * @throws ChatSessionNotFound
     */
    public function getMessages(GetChatMessagesDTO $dto): AnonymousResourceCollection
    {
        $session = $this->chatRepository->findSessionByUuid($dto->sessionUuid, $dto->companyId);

        if (!$session) {
            throw new ChatSessionNotFound('Chat session not found');
        }

        $messages = $this->chatRepository->getMessagesForSession($session->id);

        return ChatMessageResource::collection($messages);
    }

    /**
     * Submit feedback for a message.
     * Simple DB write - handled directly.
     *
     * @param SubmitFeedbackDTO $dto
     * @return bool
     * @throws ChatMessageNotFound
     */
    public function submitFeedback(SubmitFeedbackDTO $dto): bool
    {
        $message = $this->chatRepository->findMessageById($dto->messageId);

        if (!$message) {
            throw new ChatMessageNotFound('Chat message not found');
        }

        // Check if feedback already exists
        $existingFeedback = $this->feedbackRepository->findByMessageId($message->id);

        if ($existingFeedback) {
            // Update existing feedback
            $this->feedbackRepository->update($existingFeedback->id, [
                'feedback_type' => $dto->type,
                'rating' => $dto->rating,
                'comment' => $dto->comment,
                'updated_at' => now(),
            ]);
        } else {
            // Create new feedback
            $this->feedbackRepository->create([
                'message_id' => $message->id,
                'session_id' => $message->session_id,
                'feedback_type' => $dto->type,
                'rating' => $dto->rating,
                'comment' => $dto->comment,
                'created_at' => now(),
            ]);
        }

        return true;
    }
}
