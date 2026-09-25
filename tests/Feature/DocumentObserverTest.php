<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Observers\DocumentObserver;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentObserverTest extends TestCase
{
    public function test_observer_assigns_uuid_when_missing(): void
    {
        $document = new Document();

        (new DocumentObserver())->creating($document);

        $this->assertTrue(Str::isUuid((string) $document->uuid));
    }

    public function test_observer_keeps_an_explicit_uuid(): void
    {
        $document = new Document(['uuid' => 'fixed-uuid']);

        (new DocumentObserver())->creating($document);

        $this->assertSame('fixed-uuid', $document->uuid);
    }

    public function test_observer_is_registered_via_observed_by_attribute(): void
    {
        $document = new Document();

        Document::getEventDispatcher()->until('eloquent.creating: ' . Document::class, $document);

        $this->assertTrue(Str::isUuid((string) $document->uuid));
    }
}
