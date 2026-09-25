<?php

namespace App\Domains\RAG\Attributes;

use Attribute;

/**
 * Registers a VectorStore implementation with VectorStoreManager under the given driver name.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class VectorStoreDriver extends DriverAttribute {}
