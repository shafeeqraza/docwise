<?php

namespace App\Repositories\V1\Contracts;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Database\Eloquent\Collection;

interface ChatRepositoryInterface
{
    /**
     * Find chat session by UUID and company ID.
     *
     * @param string $uuid
     * @param int $companyId
     * @return ChatSession|null
     */
    public function findSessionByUuid(string $uuid, int $companyId): ?ChatSession;

    /**
     * Create a new chat session.
     *
     * @param array $data
     * @return ChatSession
     */
    public function createSession(array $data): ChatSession;

    /**
     * Get messages for a session.
     *
     * @param int $sessionId
     * @param int $limit
     * @return Collection
     */
    public function getMessagesForSession(int $sessionId, int $limit = 10): Collection;

    /**
     * Create a chat message.
     *
     * @param array $data
     * @return ChatMessage
     */
    public function createMessage(array $data): ChatMessage;

    /**
     * Find a chat message by ID within a session.
     *
     * @param int $messageId
     * @param int $sessionId
     * @return ChatMessage|null
     */
    public function findMessageInSession(int $messageId, int $sessionId): ?ChatMessage;

    /**
     * Update session.
     *
     * @param ChatSession $session
     * @param array $data
     * @return bool
     */
    public function updateSession(ChatSession $session, array $data): bool;
}
