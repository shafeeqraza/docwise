<?php

namespace App\Repositories\V1;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Repositories\V1\Contracts\ChatRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ChatRepository implements ChatRepositoryInterface
{
    /**
     * Find chat session by UUID and company ID.
     *
     * @param string $uuid
     * @param int $companyId
     * @return ChatSession|null
     */
    #[\Override]
    public function findSessionByUuid(string $uuid, int $companyId): ?ChatSession
    {
        return ChatSession::where('uuid', '=', $uuid)
            ->where('company_id', '=', $companyId)
            ->first();
    }

    /**
     * Create a new chat session.
     *
     * @param array $data
     * @return ChatSession
     */
    #[\Override]
    public function createSession(array $data): ChatSession
    {
        return ChatSession::create($data);
    }

    /**
     * Get messages for a session.
     *
     * @param int $sessionId
     * @param int $limit
     * @return Collection
     */
    #[\Override]
    public function getMessagesForSession(int $sessionId, int $limit = 10): Collection
    {
        return ChatMessage::where('session_id', '=', $sessionId)
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Create a chat message.
     *
     * @param array $data
     * @return ChatMessage
     */
    #[\Override]
    public function createMessage(array $data): ChatMessage
    {
        return ChatMessage::create($data);
    }

    /**
     * Find chat message by ID.
     *
     * @param int $messageId
     * @return ChatMessage|null
     */
    #[\Override]
    public function findMessageById(int $messageId): ?ChatMessage
    {
        return ChatMessage::find($messageId);
    }

    /**
     * Update session.
     *
     * @param ChatSession $session
     * @param array $data
     * @return bool
     */
    #[\Override]
    public function updateSession(ChatSession $session, array $data): bool
    {
        return $session->update($data);
    }
}
