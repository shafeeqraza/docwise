<?php

namespace Tests\Feature;

use App\Events\ChatMessageAnswered;
use App\Events\DocumentProcessed;
use App\Events\DocumentUploaded;
use App\Listeners\ProcessDocumentUploaded;
use App\Listeners\RecordChatUsage;
use App\Listeners\RecordDocumentUsage;
use App\Listeners\UpdateChatSessionStats;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Listeners are auto-discovered (EventServiceProvider::$listen is empty),
 * so this pins the wiring that discovery is expected to produce.
 */
class EventWiringTest extends TestCase
{
    public function test_listeners_are_discovered_for_each_domain_event(): void
    {
        Event::fake();

        Event::assertListening(DocumentUploaded::class, ProcessDocumentUploaded::class);
        Event::assertListening(DocumentProcessed::class, RecordDocumentUsage::class);
        Event::assertListening(ChatMessageAnswered::class, UpdateChatSessionStats::class);
        Event::assertListening(ChatMessageAnswered::class, RecordChatUsage::class);
    }
}
