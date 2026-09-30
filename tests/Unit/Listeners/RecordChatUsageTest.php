<?php

namespace Tests\Unit\Listeners;

use App\Domains\RAG\Services\UsageMetricService;
use App\Events\ChatMessageAnswered;
use App\Listeners\RecordChatUsage;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Mockery;
use Tests\TestCase;

class RecordChatUsageTest extends TestCase
{
    public function test_usage_is_recorded_against_the_session_company(): void
    {
        $session = (new ChatSession)->forceFill(['id' => 7, 'company_id' => 3]);
        $message = (new ChatMessage)->forceFill(['tokens_prompt' => 300, 'tokens_completion' => 50]);

        $usageMetricService = Mockery::mock(UsageMetricService::class);
        $usageMetricService->shouldReceive('recordChatUsage')->once()->with(3, 300, 50);

        $listener = new RecordChatUsage($usageMetricService);

        $this->assertInstanceOf(ShouldQueue::class, $listener);

        $listener->handle(new ChatMessageAnswered($session, $message));
    }
}
