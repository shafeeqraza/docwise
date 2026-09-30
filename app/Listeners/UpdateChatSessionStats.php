<?php

namespace App\Listeners;

use App\Events\ChatMessageAnswered;
use App\Services\V1\Chat\Session\SessionStatsUpdater;

/**
 * Updates session message and token counts after an answer.
 *
 * Runs synchronously: SendMessage refreshes the session right after
 * dispatching and returns these stats in the response.
 */
class UpdateChatSessionStats
{
    public function __construct(
        private readonly SessionStatsUpdater $statsUpdater
    ) {}

    public function handle(ChatMessageAnswered $event): void
    {
        $this->statsUpdater->update($event->session, $event->assistantMessage);
    }
}
