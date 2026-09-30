<?php

namespace Tests\Unit\Listeners;

use App\Events\ChatMessageAnswered;
use App\Listeners\UpdateChatSessionStats;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Repositories\V1\Contracts\ChatRepositoryInterface;
use App\Services\V1\Chat\Session\SessionStatsUpdater;
use Mockery;
use Tests\TestCase;

class UpdateChatSessionStatsTest extends TestCase
{
    public function test_session_counts_the_exchange_and_its_tokens(): void
    {
        $session = (new ChatSession)->forceFill(['id' => 7, 'message_count' => 4, 'total_tokens' => 1000]);
        $message = (new ChatMessage)->forceFill(['tokens_prompt' => 300, 'tokens_completion' => 50]);

        $chatRepository = Mockery::mock(ChatRepositoryInterface::class);
        $chatRepository->shouldReceive('updateSession')
            ->once()
            ->with($session, ['message_count' => 6, 'total_tokens' => 1350])
            ->andReturnTrue();

        $listener = new UpdateChatSessionStats(new SessionStatsUpdater($chatRepository));

        $listener->handle(new ChatMessageAnswered($session, $message));
    }
}
