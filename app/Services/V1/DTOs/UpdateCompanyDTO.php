<?php

namespace App\Services\V1\DTOs;

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
        public ?string $status = null,
        public ?string $subscriptionPlan = null,
        public ?string $billingCycle = null,
        public ?string $paymentStatus = null,
        public ?bool $allowOverages = null,
        public ?array $settings = null
    ) {}
}
