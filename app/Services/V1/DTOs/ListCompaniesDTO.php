<?php

namespace App\Services\V1\DTOs;

use App\Enums\CompanyPaymentStatus;
use App\Enums\CompanyStatus;

/**
 * Data Transfer Object for listing companies with filters.
 */
final readonly class ListCompaniesDTO
{
    public function __construct(
        public ?CompanyStatus $status = null,
        public ?string $subscriptionPlan = null,
        public ?CompanyPaymentStatus $paymentStatus = null,
        public ?string $search = null,
        public int $perPage = 15
    ) {}
}
