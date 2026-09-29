<?php

namespace App\Services\V1\DTOs;

/**
 * Data Transfer Object for getting chat messages.
 */
final readonly class GetChatMessagesDTO
{
    public function __construct(
        public int $companyId,
        public string $sessionUuid
    ) {}
}
