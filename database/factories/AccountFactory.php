<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'trial_ends_at' => now()->addDays(config('swiftfox.trial.days', 14)),
            'subscription_status' => Account::STATUS_TRIAL,
            'conversations_used' => 0,
            'conversations_limit' => config('swiftfox.trial.conversation_limit', 100),
            'timezone' => 'UTC',
        ];
    }

    /**
     * Create an account with an active subscription.
     */
    public function subscribed(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_status' => Account::STATUS_ACTIVE,
            'trial_ends_at' => now()->subDays(30), // Trial ended
        ]);
    }

    /**
     * Create an account with expired trial.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_status' => Account::STATUS_EXPIRED,
            'trial_ends_at' => now()->subDays(1),
        ]);
    }

    /**
     * Create an account that has reached its conversation limit.
     */
    public function limitReached(): static
    {
        return $this->state(fn (array $attributes) => [
            'conversations_used' => $attributes['conversations_limit'] ?? 100,
        ]);
    }
}
