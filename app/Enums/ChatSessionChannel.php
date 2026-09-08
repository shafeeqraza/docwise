<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ChatSessionChannel: string
{
    use HasValues;

    case WEB = 'web';
    case API = 'api';
    case WIDGET = 'widget';
    case SLACK = 'slack';
    case TEAMS = 'teams';
}
