<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create a super admin
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@swiftfox.cloud',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SUPER_ADMIN,
            'account_id' => null,
            'email_verified_at' => now(),
        ]);

        // Create a demo account with owner
        $authService = new AuthService();
        $result = $authService->register([
            'name' => 'Demo Owner',
            'email' => 'demo@swiftfox.cloud',
            'password' => 'password',
            'company_name' => 'Demo Company',
            'timezone' => 'UTC',
        ]);

        // Mark email as verified
        $result['user']->email_verified_at = now();
        $result['user']->save();

        // Create a member for the demo account
        User::create([
            'name' => 'Demo Member',
            'email' => 'member@swiftfox.cloud',
            'password' => Hash::make('password'),
            'role' => User::ROLE_MEMBER,
            'account_id' => $result['account']->id,
            'email_verified_at' => now(),
        ]);

        $this->command->info('Database seeded successfully!');
        $this->command->info('');
        $this->command->info('Demo Accounts:');
        $this->command->info('  Super Admin: admin@swiftfox.cloud / password');
        $this->command->info('  Owner: demo@swiftfox.cloud / password');
        $this->command->info('  Member: member@swiftfox.cloud / password');
    }
}
