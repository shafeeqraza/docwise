<?php

namespace App\Services\V1\DTOs;

/**
 * Data Transfer Object for listing companies with filters.
 */
readonly class ListCompaniesDTO
{
    public function __construct(
        public ?string $status = null,
        public ?string $subscriptionPlan = null,
        public ?string $paymentStatus = null,
        public ?string $search = null,
        public int $perPage = 15
    ) {}
}
