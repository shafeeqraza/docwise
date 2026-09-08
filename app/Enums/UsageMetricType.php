<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum UsageMetricType: string
{
    use HasValues;

    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
}
