<?php

namespace App\Services\V1\Chat\Session;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Repositories\V1\Contracts\ChatRepositoryInterface;

/**
 * Service for updating session statistics.
 *
 * Follows Single Responsibility Principle (SRP): Only responsible for
 * updating session statistics after message generation.
 */
class SessionStatsUpdater
{
    public function __construct(
        private readonly ChatRepositoryInterface $chatRepository
    ) {}

    /**
     * Update session statistics after message generation.
     *
     * @param ChatSession $session
     * @param ChatMessage $assistantMessage The persisted assistant reply
     * @return void
     */
    public function update(ChatSession $session, ChatMessage $assistantMessage): void
    {
        $this->chatRepository->updateSession($session, [
            'message_count' => $session->message_count + 2, // User + assistant
            'total_tokens' => $session->total_tokens
                + (int) $assistantMessage->tokens_prompt
                + (int) $assistantMessage->tokens_completion,
        ]);
    }
}
