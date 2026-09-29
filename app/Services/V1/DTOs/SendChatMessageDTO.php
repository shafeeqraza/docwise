<?php

namespace App\Services\V1\DTOs;

/**
 * Data Transfer Object for sending a chat message.
 */
final readonly class SendChatMessageDTO
{
    public function __construct(
        public int $companyId,
        public string $message,
        public ?string $sessionId = null,
        public ?array $userMetadata = null,
        public ?string $llmModel = null,
        public ?string $embeddingModel = null,
        public ?int $maxContextChunks = null,
        public ?int $maxChatHistory = null,
        public ?float $temperature = null,
        public ?int $maxTokens = null
    ) {}
}
