<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum DocumentVersionProcessingState: string
{
    use HasValues;

    case PENDING = 'pending';
    case PARSING = 'parsing';
    case CHUNKING = 'chunking';
    case EMBEDDING = 'embedding';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    /**
     * States a version moves through, in order, on the way to completion.
     *
     * FAILED is deliberately absent: it is a terminal state off the happy path,
     * not a position within it.
     *
     * @return array<int, self>
     */
    public static function progression(): array
    {
        return [self::PENDING, self::PARSING, self::CHUNKING, self::EMBEDDING, self::COMPLETED];
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED], true);
    }
}
