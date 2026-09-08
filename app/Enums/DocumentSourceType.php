<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum DocumentSourceType: string
{
    use HasValues;

    case UPLOAD = 'upload';
    case URL = 'url';
    case API = 'api';
}
