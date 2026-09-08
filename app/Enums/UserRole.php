<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum UserRole: string
{
    use HasValues;

    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case AGENT = 'agent';
    case API_USER = 'api_user';
}
