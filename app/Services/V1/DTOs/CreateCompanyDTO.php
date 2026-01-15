<?php

namespace App\Services\V1\DTOs;

/**
 * Data Transfer Object for creating a company.
 */
readonly class CreateCompanyDTO
{
    public function __construct(
        public string $name,
        public ?string $slug = null,
        public ?string $email = null,
        public ?string $phone = null,
        public string $status = 'active',
        public string $subscriptionPlan = 'basic',
        public string $billingCycle = 'monthly',
        public string $paymentStatus = 'active',
        public bool $allowOverages = false,
        public ?array $settings = null
    ) {}
}
