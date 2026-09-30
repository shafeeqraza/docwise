<?php

namespace Tests\Unit\Services;

use App\Domains\RAG\DTOs\ChatRagResult;
use App\Events\ChatMessageAnswered;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\V1\Chat\Messages\ChatHistoryProvider;
use App\Services\V1\Chat\Messages\MessageWriter;
use App\Services\V1\Chat\RAG\ChatRagRequestFactory;
use App\Services\V1\Chat\RAG\ChatRagResponder;
use App\Services\V1\Chat\RAG\RetrievedChunksPersister;
use App\Services\V1\Chat\Session\SessionResolver;
use App\Services\V1\Chat\UseCases\SendMessage;
use App\Services\V1\DTOs\SendChatMessageDTO;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class SendMessageTest extends TestCase
{
    public function test_answering_a_message_dispatches_chat_message_answered(): void
    {
        Event::fake([ChatMessageAnswered::class]);

        $session = (new ChatSession)->forceFill(['id' => 7, 'company_id' => 3]);
        $assistantMessage = (new ChatMessage)->forceFill(['id' => 42, 'session_id' => 7]);
        $ragResult = new ChatRagResult(
            content: 'Answer',
            model: 'gemini-2.0-flash',
            tokensPrompt: 300,
            tokensCompletion: 50,
            finishReason: 'stop',
            retrievedChunks: [],
            citations: [],
            confidenceScore: 0.9,
        );

        $sessionResolver = Mockery::mock(SessionResolver::class);
        $sessionResolver->shouldReceive('resolve')->andReturn($session);

        $messageWriter = Mockery::mock(MessageWriter::class);
        $messageWriter->shouldReceive('writeUserMessage')->once()->with(7, 'Hello');
        $messageWriter->shouldReceive('writeAssistantMessage')->once()->with(7, $ragResult)->andReturn($assistantMessage);

        $historyProvider = Mockery::mock(ChatHistoryProvider::class);
        $historyProvider->shouldReceive('getHistory')->andReturn([]);

        $requestFactory = Mockery::mock(ChatRagRequestFactory::class);
        $requestFactory->shouldReceive('createPipelineParams')->andReturn([]);

        $responder = Mockery::mock(ChatRagResponder::class);
        $responder->shouldReceive('execute')->andReturn($ragResult);

        $chunksPersister = Mockery::mock(RetrievedChunksPersister::class);
        $chunksPersister->shouldReceive('persist')->once()->with(42, []);

        $useCase = new SendMessage(
            $sessionResolver,
            $messageWriter,
            $historyProvider,
            $requestFactory,
            $responder,
            $chunksPersister,
        );

        $useCase->execute(new SendChatMessageDTO(companyId: 3, message: 'Hello'));

        Event::assertDispatched(ChatMessageAnswered::class, fn (ChatMessageAnswered $event) => $event->session === $session
            && $event->assistantMessage === $assistantMessage);
    }
}
