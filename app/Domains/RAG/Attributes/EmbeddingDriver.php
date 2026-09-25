<?php

namespace App\Domains\RAG\Attributes;

use Attribute;

/**
 * Registers an EmbeddingProvider implementation with EmbeddingProviderFactory under the given driver name.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class EmbeddingDriver extends DriverAttribute {}
