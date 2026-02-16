<?php

namespace App\Repositories\V1;

use App\Models\Feedback;
use App\Repositories\V1\Contracts\FeedbackRepositoryInterface;

/**
 * Repository for feedback data access.
 *
 * Follows Single Responsibility Principle (SRP): Only responsible for
 * data access operations for feedback.
 */
class FeedbackRepository implements FeedbackRepositoryInterface
{
    /**
     * Find feedback by message ID.
     *
     * @param int $messageId Message ID
     * @return Feedback|null
     */
    public function findByMessageId(int $messageId): ?Feedback
    {
        return Feedback::where('message_id', $messageId)->first();
    }

    /**
     * Create a new feedback record.
     *
     * @param array $data Feedback data
     * @return Feedback
     */
    public function create(array $data): Feedback
    {
        return Feedback::create($data);
    }

    /**
     * Update an existing feedback record.
     *
     * @param int $id Feedback ID
     * @param array $data Update data
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $feedback = Feedback::find($id);

        if (!$feedback) {
            return false;
        }

        return $feedback->update($data);
    }
}
