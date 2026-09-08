<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ChatSessionStatus: string
{
    use HasValues;

    case ACTIVE = 'active';
    case RESOLVED = 'resolved';
    case ESCALATED = 'escalated';
    case ARCHIVED = 'archived';
}
