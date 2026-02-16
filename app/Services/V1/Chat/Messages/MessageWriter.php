<?php

namespace App\Services\V1\Chat\Messages;

use App\Domains\RAG\DTOs\ChatRagResult;
use App\Models\ChatMessage;
use App\Repositories\V1\Contracts\ChatRepositoryInterface;

/**
 * Service for writing chat messages.
 *
 * Follows Single Responsibility Principle (SRP): Only responsible for
 * persisting user and assistant messages.
 */
class MessageWriter
{
    public function __construct(
        private readonly ChatRepositoryInterface $chatRepository
    ) {}

    /**
     * Write user message.
     *
     * @param int $sessionId Session ID
     * @param string $content Message content
     * @return ChatMessage Created message
     */
    public function writeUserMessage(int $sessionId, string $content): ChatMessage
    {
        return $this->chatRepository->createMessage([
            'session_id' => $sessionId,
            'role' => 'user',
            'content' => $content,
            'created_at' => now(),
        ]);
    }

    /**
     * Write assistant message from RAG result.
     *
     * @param int $sessionId Session ID
     * @param ChatRagResult $ragResult RAG result
     * @return ChatMessage Created message
     */
    public function writeAssistantMessage(int $sessionId, ChatRagResult $ragResult): ChatMessage
    {
        return $this->chatRepository->createMessage([
            'session_id' => $sessionId,
            'role' => 'assistant',
            'content' => $ragResult->content,
            'tokens_prompt' => $ragResult->tokensPrompt,
            'tokens_completion' => $ragResult->tokensCompletion,
            'model_used' => $ragResult->model,
            'confidence_score' => $ragResult->confidenceScore,
            'citations' => $ragResult->citations,
            'created_at' => now(),
        ]);
    }
}
