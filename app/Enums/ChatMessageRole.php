<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ChatMessageRole: string
{
    use HasValues;

    case USER = 'user';
    case ASSISTANT = 'assistant';
    case SYSTEM = 'system';
}
