<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'account_id' => null,
            'role' => User::ROLE_MEMBER,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create a user as an owner.
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_OWNER,
        ]);
    }

    /**
     * Create a user as a member.
     */
    public function member(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_MEMBER,
        ]);
    }

    /**
     * Create a user as a super admin.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_id' => null,
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
    }

    /**
     * Associate the user with an account.
     */
    public function forAccount(Account $account): static
    {
        return $this->state(fn (array $attributes) => [
            'account_id' => $account->id,
        ]);
    }
}
