<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum CompanyStatus: string
{
    use HasValues;

    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case TRIAL = 'trial';
}
