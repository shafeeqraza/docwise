<?php

namespace App\Listeners;

use App\Domains\RAG\Services\UsageMetricService;
use App\Events\ChatMessageAnswered;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Records LLM usage for an answered chat message against the session's company.
 */
class RecordChatUsage implements ShouldQueue
{
    public function __construct(
        private readonly UsageMetricService $usageMetricService
    ) {}

    public function handle(ChatMessageAnswered $event): void
    {
        $this->usageMetricService->recordChatUsage(
            $event->session->company_id,
            (int) $event->assistantMessage->tokens_prompt,
            (int) $event->assistantMessage->tokens_completion
        );
    }
}
