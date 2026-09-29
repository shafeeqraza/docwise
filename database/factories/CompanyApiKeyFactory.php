<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CompanyApiKey>
 */
class CompanyApiKeyFactory extends Factory
{
    /**
     * Define the model's default state. The uuid is filled by CompanyApiKey::boot().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $keyData = CompanyApiKey::generateKey('cs_test');

        return [
            'company_id' => Company::factory(),
            'name' => fake()->words(2, true),
            'key_hash' => $keyData['hash'],
            'key_prefix' => $keyData['prefix'],
            'permissions' => ['widget:chat'],
            'allowed_domain' => fake()->domainName(),
            'is_active' => true,
        ];
    }
}
