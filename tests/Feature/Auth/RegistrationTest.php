<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'Test Company',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'account',
                    ],
                    'token',
                ],
            ]);

        // Verify user was created with correct role
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'role' => User::ROLE_OWNER,
        ]);

        // Verify account was created with trial
        $this->assertDatabaseHas('accounts', [
            'name' => 'Test Company',
            'subscription_status' => Account::STATUS_TRIAL,
        ]);

        // Verify business hours were created
        $account = Account::where('name', 'Test Company')->first();
        $this->assertCount(7, $account->businessHours);
    }

    public function test_registration_requires_valid_data(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'company_name']);
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'Test Company',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_trial_starts_with_correct_limits(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'Test Company',
        ]);

        $response->assertStatus(201);

        $account = Account::where('name', 'Test Company')->first();

        $this->assertEquals(0, $account->conversations_used);
        $this->assertEquals(100, $account->conversations_limit);
        $this->assertTrue($account->isOnTrial());
        // Trial days should be between 13-14 depending on time of day
        $this->assertGreaterThanOrEqual(13, $account->getRemainingTrialDays());
        $this->assertLessThanOrEqual(14, $account->getRemainingTrialDays());
    }
}
