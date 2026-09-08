<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ChatMessageContentType: string
{
    use HasValues;

    case TEXT = 'text';
    case MARKDOWN = 'markdown';
    case HTML = 'html';
}
