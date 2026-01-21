<?php

namespace App\Services\V1\DTOs;

/**
 * Data Transfer Object for submitting feedback.
 */
readonly class SubmitFeedbackDTO
{
    public function __construct(
        public int $companyId,
        public string $sessionUuid,
        public int $messageId,
        public string $type,
        public ?int $rating = null,
        public ?string $comment = null
    ) {}
}
