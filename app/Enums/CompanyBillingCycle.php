<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum CompanyBillingCycle: string
{
    use HasValues;

    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';
}
