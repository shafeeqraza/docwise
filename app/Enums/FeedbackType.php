<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum FeedbackType: string
{
    use HasValues;

    case THUMBS_UP = 'thumbs_up';
    case THUMBS_DOWN = 'thumbs_down';
    case RATING = 'rating';
    case COMMENT = 'comment';
}
