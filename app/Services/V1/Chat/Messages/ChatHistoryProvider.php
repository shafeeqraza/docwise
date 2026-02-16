<?php

namespace App\Services\V1\Chat\Messages;

use App\Models\ChatSession;
use App\Repositories\V1\Contracts\ChatRepositoryInterface;

/**
 * Service for providing chat history.
 *
 * Follows Single Responsibility Principle (SRP): Only responsible for
 * fetching and formatting chat history for context.
 */
class ChatHistoryProvider
{
    public function __construct(
        private readonly ChatRepositoryInterface $chatRepository
    ) {}

    /**
     * Get chat history for context.
     *
     * @param ChatSession $session
     * @param int $maxHistory Maximum number of history messages
     * @return array<int, array{role: string, content: string}>
     */
    public function getHistory(ChatSession $session, int $maxHistory): array
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
