<?php

namespace App\Services\V1\Chat\UseCases;

use App\Events\ChatMessageAnswered;
use App\Http\Resources\ChatSessionResource;
use App\Services\V1\Chat\Messages\ChatHistoryProvider;
use App\Services\V1\Chat\Messages\MessageWriter;
use App\Services\V1\Chat\RAG\ChatRagRequestFactory;
use App\Services\V1\Chat\RAG\ChatRagResponder;
use App\Services\V1\Chat\RAG\RetrievedChunksPersister;
use App\Services\V1\Chat\Session\SessionResolver;
use App\Services\V1\DTOs\SendChatMessageDTO;

/**
 * Use case for sending a chat message and generating AI response.
 *
 * Follows Single Responsibility Principle (SRP): Only orchestrates the
 * send message flow by coordinating specialized services.
 */
class SendMessage
{
    public function __construct(
        private readonly SessionResolver $sessionResolver,
        private readonly MessageWriter $messageWriter,
        private readonly ChatHistoryProvider $historyProvider,
        private readonly ChatRagRequestFactory $ragRequestFactory,
        private readonly ChatRagResponder $ragResponder,
        private readonly RetrievedChunksPersister $chunksPersister
    ) {}

    /**
     * Execute the send message use case.
     *
     * @param SendChatMessageDTO $dto
     * @return ChatSessionResource
     */
    public function execute(SendChatMessageDTO $dto): ChatSessionResource
    {
        // Get or create session
        $session = $this->sessionResolver->resolve($dto);

        // Create user message
        $this->messageWriter->writeUserMessage($session->id, $dto->message);

        // Get chat history
        $maxHistory = $dto->maxChatHistory ?? config('chat.max_chat_history', 10);
        $chatHistory = $this->historyProvider->getHistory($session, $maxHistory);

        // Create RAG pipeline parameters
        $pipelineParams = $this->ragRequestFactory->createPipelineParams($dto, $session, $chatHistory);

        // Execute RAG pipeline
        $ragResult = $this->ragResponder->execute($pipelineParams);

        // Create assistant message
        $assistantMessage = $this->messageWriter->writeAssistantMessage($session->id, $ragResult);

        // Store retrieved chunks
        $this->chunksPersister->persist($assistantMessage->id, $ragResult->retrievedChunks);

        // Session stats (sync) and usage metrics (queued) are handled by listeners
        ChatMessageAnswered::dispatch($session, $assistantMessage);

        // Refresh session and load latest message
        $session->refresh();
        $session->setRelation('latestMessage', $assistantMessage);

        return new ChatSessionResource($session);
    }
}
