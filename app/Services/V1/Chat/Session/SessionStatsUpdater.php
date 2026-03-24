<?php

namespace App\Services\V1\Chat\Session;

use App\Domains\RAG\DTOs\ChatRagResult;
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
     * @param ChatRagResult $ragResult
     * @return void
     */
    public function update(ChatSession $session, ChatRagResult $ragResult): void
    {
        $this->chatRepository->updateSession($session, [
            'message_count' => $session->message_count + 2, // User + assistant
            'total_tokens' => $session->total_tokens + $ragResult->tokensPrompt + $ragResult->tokensCompletion,
        ]);
    }
}
