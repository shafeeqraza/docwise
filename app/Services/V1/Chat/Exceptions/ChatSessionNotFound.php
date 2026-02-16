<?php

namespace App\Services\V1\Chat\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Exception thrown when a chat session is not found.
 */
class ChatSessionNotFound extends ModelNotFoundException
{
    public function __construct(string $message = 'Chat session not found', int $code = 404, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
