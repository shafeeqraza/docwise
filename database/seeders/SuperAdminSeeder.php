<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create or get a system company for super admins
        // This is needed due to foreign key constraint on company_id
        $systemCompany = Company::firstOrCreate(
            ['slug' => 'system'],
            [
                'uuid' => Str::uuid(),
                'name' => 'System',
                'slug' => 'system',
                'status' => 'active',
                'subscription_plan' => 'system',
                'billing_cycle' => 'monthly',
                'payment_status' => 'active',
            ]
        );

        $superAdmins = [
            [
                'uuid' => Str::uuid(),
                'name' => 'Super Admin',
                'email' => 'superadmin@example.com',
                'password' => Hash::make('password123'),
                'role' => 'super_admin',
                'is_super_admin' => true,
                'can_impersonate' => true,
                'company_id' => $systemCompany->id,
                'email_verified_at' => now(),
            ],
            [
                'uuid' => Str::uuid(),
                'name' => 'System Administrator',
                'email' => 'admin@system.com',
                'password' => Hash::make('admin123'),
                'role' => 'super_admin',
                'is_super_admin' => true,
                'can_impersonate' => true,
                'company_id' => $systemCompany->id,
                'email_verified_at' => now(),
            ],
        ];

        foreach ($superAdmins as $admin) {
            // Check if user already exists
            $existingUser = User::where('email', $admin['email'])->first();

            if (!$existingUser) {
                User::create($admin);
                $this->command->info("Created super admin: {$admin['email']}");
            } else {
                // Update existing user to be super admin
                $existingUser->update([
                    'role' => 'super_admin',
                    'is_super_admin' => true,
                    'can_impersonate' => true,
                    'company_id' => $systemCompany->id,
                ]);
                $this->command->info("Updated user to super admin: {$admin['email']}");
            }
        }

        $this->command->info('Super admin users seeded successfully!');
        $this->command->warn('Default passwords: password123 and admin123 - Please change these in production!');
    }
}
