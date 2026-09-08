<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum CompanyPaymentStatus: string
{
    use HasValues;

    case ACTIVE = 'active';
    case PAST_DUE = 'past_due';
    case CANCELLED = 'cancelled';
}
