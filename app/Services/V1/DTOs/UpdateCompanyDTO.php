<?php

namespace App\Services\V1\DTOs;

use App\Enums\CompanyBillingCycle;
use App\Enums\CompanyPaymentStatus;
use App\Enums\CompanyStatus;

/**
 * Data Transfer Object for updating a company.
 */
readonly class UpdateCompanyDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $slug = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?CompanyStatus $status = null,
        public ?string $subscriptionPlan = null,
        public ?CompanyBillingCycle $billingCycle = null,
        public ?CompanyPaymentStatus $paymentStatus = null,
        public ?bool $allowOverages = null,
        public ?array $settings = null
    ) {}
}
