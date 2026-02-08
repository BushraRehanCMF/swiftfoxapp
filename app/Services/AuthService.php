<?php

namespace App\Services;

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * Register a new account with owner user.
     *
     * @param array $data
     * @return array{account: Account, user: User}
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // Create the account with trial
            $account = Account::create([
                'name' => $data['company_name'],
                'trial_ends_at' => now()->addDays(config('swiftfox.trial.days', 14)),
                'subscription_status' => Account::STATUS_TRIAL,
                'conversations_used' => 0,
                'conversations_limit' => config('swiftfox.trial.conversation_limit', 100),
                'timezone' => $data['timezone'] ?? 'UTC',
            ]);

            // Create the owner user
            $user = User::create([
                'account_id' => $account->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => User::ROLE_OWNER,
            ]);

            // Create default business hours (Mon-Fri 9am-5pm)
            $this->createDefaultBusinessHours($account);

            return [
                'account' => $account,
                'user' => $user,
            ];
        });
    }

    /**
     * Create default business hours for an account.
     */
    protected function createDefaultBusinessHours(Account $account): void
    {
        $defaultHours = [
            ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true], // Monday
            ['day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true], // Tuesday
            ['day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true], // Wednesday
            ['day_of_week' => 4, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true], // Thursday
            ['day_of_week' => 5, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true], // Friday
            ['day_of_week' => 6, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => false], // Saturday
            ['day_of_week' => 0, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => false], // Sunday
        ];

        foreach ($defaultHours as $hours) {
            $account->businessHours()->create($hours);
        }
    }

    /**
     * Create a super admin user (no account).
     */
    public function createSuperAdmin(array $data): User
    {
        return User::create([
            'account_id' => null,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
    }
}
