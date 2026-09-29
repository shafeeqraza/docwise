<?php

namespace Tests\Unit\Services;

use App\Enums\FeedbackType;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Repositories\V1\Contracts\ChatRepositoryInterface;
use App\Repositories\V1\Contracts\FeedbackRepositoryInterface;
use App\Services\V1\Chat\ChatService;
use App\Services\V1\Chat\Exceptions\ChatMessageNotFound;
use App\Services\V1\Chat\Exceptions\ChatSessionNotFound;
use App\Services\V1\Chat\UseCases\SendMessage;
use App\Services\V1\DTOs\SubmitFeedbackDTO;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ChatServiceSubmitFeedbackTest extends TestCase
{
    private ChatRepositoryInterface&MockInterface $chatRepository;

    private FeedbackRepositoryInterface&MockInterface $feedbackRepository;

    private ChatService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chatRepository = Mockery::mock(ChatRepositoryInterface::class);
        $this->feedbackRepository = Mockery::mock(FeedbackRepositoryInterface::class);

        $this->service = new ChatService(
            Mockery::mock(SendMessage::class),
            $this->chatRepository,
            $this->feedbackRepository,
        );
    }

    private function dto(): SubmitFeedbackDTO
    {
        return new SubmitFeedbackDTO(
            companyId: 1,
            sessionUuid: 'session-uuid',
            messageId: 42,
            type: FeedbackType::THUMBS_UP,
        );
    }

    public function test_session_outside_the_company_is_rejected(): void
    {
        $this->chatRepository->shouldReceive('findSessionByUuid')
            ->with('session-uuid', 1)
            ->andReturnNull();
        $this->chatRepository->shouldNotReceive('findMessageInSession');
        $this->feedbackRepository->shouldNotReceive('create');

        $this->expectException(ChatSessionNotFound::class);

        $this->service->submitFeedback($this->dto());
    }

    public function test_message_outside_the_session_is_rejected(): void
    {
        $session = (new ChatSession)->forceFill(['id' => 7, 'company_id' => 1]);

        $this->chatRepository->shouldReceive('findSessionByUuid')->andReturn($session);
        $this->chatRepository->shouldReceive('findMessageInSession')
            ->with(42, 7)
            ->andReturnNull();
        $this->feedbackRepository->shouldNotReceive('create');

        $this->expectException(ChatMessageNotFound::class);

        $this->service->submitFeedback($this->dto());
    }

    public function test_feedback_is_recorded_against_the_resolved_session(): void
    {
        $session = (new ChatSession)->forceFill(['id' => 7, 'company_id' => 1]);
        $message = (new ChatMessage)->forceFill(['id' => 42, 'session_id' => 7]);

        $this->chatRepository->shouldReceive('findSessionByUuid')->andReturn($session);
        $this->chatRepository->shouldReceive('findMessageInSession')->with(42, 7)->andReturn($message);
        $this->feedbackRepository->shouldReceive('findByMessageId')->with(42)->andReturnNull();
        $this->feedbackRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $data) => $data['message_id'] === 42
                && $data['session_id'] === 7
                && $data['feedback_type'] === FeedbackType::THUMBS_UP));

        $this->assertTrue($this->service->submitFeedback($this->dto()));
    }
}
