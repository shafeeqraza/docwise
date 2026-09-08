<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum IngestionJobStatus: string
{
    use HasValues;

    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}
