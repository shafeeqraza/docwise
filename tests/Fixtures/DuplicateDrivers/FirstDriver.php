<?php

namespace Tests\Fixtures\DuplicateDrivers;

use App\Domains\RAG\Attributes\VectorStoreDriver;
use Tests\Fixtures\FakeDriver;

#[VectorStoreDriver('duplicate')]
class FirstDriver implements FakeDriver {}
