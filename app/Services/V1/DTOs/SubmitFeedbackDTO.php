<?php

namespace App\Services\V1\DTOs;

use App\Enums\FeedbackType;

/**
 * Data Transfer Object for submitting feedback.
 */
readonly class SubmitFeedbackDTO
{
    public function __construct(
        public int $companyId,
        public string $sessionUuid,
        public int $messageId,
        public FeedbackType $type,
        public ?int $rating = null,
        public ?string $comment = null
    ) {}
}
