<?php

namespace App\Services\V1\DTOs;

use App\Enums\CompanyBillingCycle;
use App\Enums\CompanyPaymentStatus;
use App\Enums\CompanyStatus;

/**
 * Data Transfer Object for creating a company.
 */
final readonly class CreateCompanyDTO
{
    public function __construct(
        public string $name,
        public ?string $slug = null,
        public ?string $email = null,
        public ?string $phone = null,
        public CompanyStatus $status = CompanyStatus::ACTIVE,
        public string $subscriptionPlan = 'basic',
        public CompanyBillingCycle $billingCycle = CompanyBillingCycle::MONTHLY,
        public CompanyPaymentStatus $paymentStatus = CompanyPaymentStatus::ACTIVE,
        public bool $allowOverages = false,
        public ?array $settings = null
    ) {}
}
