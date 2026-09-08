<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum DocumentStatus: string
{
    use HasValues;

    case UPLOADED = 'uploaded';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case ARCHIVED = 'archived';
}
