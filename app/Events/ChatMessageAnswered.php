<?php

namespace App\Events;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by SendMessage once the assistant reply has been persisted.
 *
 * Token usage is read from the assistant message (tokens_prompt, tokens_completion).
 */
class ChatMessageAnswered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ChatSession $session,
        public ChatMessage $assistantMessage
    ) {}
}
