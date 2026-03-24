<?php

namespace App\Repositories\V1\Contracts;

use App\Models\Feedback;

interface FeedbackRepositoryInterface
{
    /**
     * Find feedback by message ID.
     *
     * @param int $messageId Message ID
     * @return Feedback|null
     */
    public function findByMessageId(int $messageId): ?Feedback;

    /**
     * Create a new feedback record.
     *
     * @param array $data Feedback data
     * @return Feedback
     */
    public function create(array $data): Feedback;

    /**
     * Update an existing feedback record.
     *
     * @param int $id Feedback ID
     * @param array $data Update data
     * @return bool
     */
    public function update(int $id, array $data): bool;
}
