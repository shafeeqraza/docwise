<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum BillingInvoiceStatus: string
{
    use HasValues;

    case DRAFT = 'draft';
    case SENT = 'sent';
    case PAID = 'paid';
    case OVERDUE = 'overdue';
}
