<?php

namespace App\Services\V1\Contracts;

use App\Http\Resources\ChatSessionResource;
use App\Services\V1\DTOs\GetChatMessagesDTO;
use App\Services\V1\DTOs\SendChatMessageDTO;
use App\Services\V1\DTOs\SubmitFeedbackDTO;

interface ChatServiceInterface
{
    /**
     * Send a chat message and generate AI response.
     *
     * @param SendChatMessageDTO $dto
     * @return ChatSessionResource
     */
    public function sendMessage(SendChatMessageDTO $dto): ChatSessionResource;

    /**
     * Get chat messages for a session.
     *
     * @param GetChatMessagesDTO $dto
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getMessages(GetChatMessagesDTO $dto);

    /**
     * Submit feedback for a message.
     *
     * @param SubmitFeedbackDTO $dto
     * @return bool
     */
    public function submitFeedback(SubmitFeedbackDTO $dto): bool;
}
