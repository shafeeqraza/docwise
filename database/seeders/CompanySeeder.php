<?php

namespace Database\Seeders;

use App\Enums\CompanyBillingCycle;
use App\Enums\CompanyPaymentStatus;
use App\Enums\CompanyStatus;
use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = [
            [
                'name' => 'Acme Corporation',
                'slug' => 'acme-corp',
                'email' => 'contact@acme.com',
                'phone' => '+1-555-0100',
                'status' => CompanyStatus::ACTIVE,
                'subscription_plan' => 'professional',
                'billing_cycle' => CompanyBillingCycle::MONTHLY,
                'next_billing_date' => now()->addMonth(),
                'payment_status' => CompanyPaymentStatus::ACTIVE,
                'allow_overages' => true,
                'settings' => [
                    'max_documents' => 500,
                    'embedding_model' => 'models/gemini-embedding-001',
                    'chunk_size' => 512,
                    'chunk_overlap' => 120,
                    'theme' => 'light',
                ],
            ],
            [
                'name' => 'TechStart Inc',
                'slug' => 'techstart',
                'email' => 'hello@techstart.com',
                'phone' => '+1-555-0200',
                'status' => CompanyStatus::ACTIVE,
                'subscription_plan' => 'starter',
                'billing_cycle' => CompanyBillingCycle::MONTHLY,
                'next_billing_date' => now()->addMonth(),
                'payment_status' => CompanyPaymentStatus::ACTIVE,
                'allow_overages' => false,
                'settings' => [
                    'max_documents' => 50,
                    'embedding_model' => 'models/gemini-embedding-001',
                    'chunk_size' => 512,
                    'chunk_overlap' => 120,
                ],
            ],
            [
                'name' => 'Enterprise Solutions Ltd',
                'slug' => 'enterprise-solutions',
                'email' => 'enterprise@example.com',
                'phone' => '+1-555-0300',
                'status' => CompanyStatus::ACTIVE,
                'subscription_plan' => 'enterprise',
                'billing_cycle' => CompanyBillingCycle::YEARLY,
                'next_billing_date' => now()->addYear(),
                'payment_status' => CompanyPaymentStatus::ACTIVE,
                'allow_overages' => true,
                'settings' => [
                    'max_documents' => -1, // Unlimited
                    'embedding_model' => 'models/gemini-embedding-001',
                    'chunk_size' => 512,
                    'chunk_overlap' => 120,
                    'custom_features' => [
                        'sso_enabled' => true,
                        'custom_domain' => true,
                        'priority_support' => true,
                    ],
                ],
            ],
            [
                'name' => 'Trial Company',
                'slug' => 'trial-company',
                'email' => 'trial@example.com',
                'status' => CompanyStatus::TRIAL,
                'subscription_plan' => 'starter',
                'billing_cycle' => CompanyBillingCycle::MONTHLY,
                'next_billing_date' => now()->addDays(14), // 14-day trial
                'payment_status' => CompanyPaymentStatus::ACTIVE,
                'allow_overages' => false,
                'settings' => [
                    'max_documents' => 10,
                    'embedding_model' => 'models/gemini-embedding-001',
                    'chunk_size' => 512,
                    'chunk_overlap' => 120,
                ],
            ],
            [
                'name' => 'Suspended Corp',
                'slug' => 'suspended-corp',
                'email' => 'suspended@example.com',
                'status' => CompanyStatus::SUSPENDED,
                'subscription_plan' => 'professional',
                'billing_cycle' => CompanyBillingCycle::MONTHLY,
                'payment_status' => CompanyPaymentStatus::PAST_DUE,
                'allow_overages' => false,
                'settings' => [
                    'max_documents' => 500,
                    'embedding_model' => 'models/gemini-embedding-001',
                    'chunk_size' => 512,
                    'chunk_overlap' => 120,
                ],
            ],
        ];

        foreach ($companies as $companyData) {
            // Check if company already exists by slug
            $existingCompany = Company::where('slug', $companyData['slug'])->first();

            if (!$existingCompany) {
                // Generate UUID if not provided
                if (!isset($companyData['uuid'])) {
                    $companyData['uuid'] = Str::uuid();
                }

                Company::create($companyData);
                $this->command->info("Created company: {$companyData['name']} ({$companyData['slug']})");
            } else {
                $this->command->warn("Company already exists: {$companyData['slug']} - Skipping");
            }
        }

        $this->command->info('Company seeding completed!');
        $this->command->info('Total companies: ' . Company::count());
    }
}
