<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\LoginAttempt;
use App\Models\User;
use Tests\TestCase;

class SpamProtectionTest extends TestCase
{
    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = Account::create([
            'name' => 'Test Account',
            'timezone' => 'UTC',
        ]);
    }

    /** @test */
    public function user_cannot_login_with_unverified_email()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'account_id' => $this->account->id,
            'role' => 'owner',
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    /** @test */
    public function user_can_login_with_verified_email()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'verified@example.com',
            'password' => bcrypt('password123'),
            'account_id' => $this->account->id,
            'role' => 'owner',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'verified@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['token']);
    }

    /** @test */
    public function account_locks_after_5_failed_login_attempts()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'locktest@example.com',
            'password' => bcrypt('correctpassword'),
            'account_id' => $this->account->id,
            'role' => 'owner',
            'email_verified_at' => now(),
        ]);

        // Attempt login 5 times with wrong password
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'locktest@example.com',
                'password' => 'wrongpassword',
            ])->assertStatus(422);
        }

        // 6th attempt should be rejected due to lockout
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'locktest@example.com',
            'password' => 'correctpassword',
        ]);

        $response->assertStatus(429);
        $response->assertJsonPath('message', 'Too many login attempts. Please try again later.');
    }

    /** @test */
    public function failed_attempts_are_tracked_per_ip()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'iptest@example.com',
            'password' => bcrypt('password123'),
            'account_id' => $this->account->id,
            'role' => 'owner',
            'email_verified_at' => now(),
        ]);

        // All attempts from same IP
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'iptest@example.com',
                'password' => 'wrongpassword',
            ]);
        }

        $isLocked = LoginAttempt::isLocked('iptest@example.com', '127.0.0.1');
        $this->assertTrue($isLocked, 'Account should be locked after 5 failed attempts from same IP');
    }

    /** @test */
    public function successful_login_clears_failed_attempts()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'cleartest@example.com',
            'password' => bcrypt('password123'),
            'account_id' => $this->account->id,
            'role' => 'owner',
            'email_verified_at' => now(),
        ]);

        // Record some failed attempts
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'cleartest@example.com',
                'password' => 'wrongpassword',
            ]);
        }

        // Successful login
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'cleartest@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);

        // Verify attempts are cleared
        $isLocked = LoginAttempt::isLocked('cleartest@example.com', '127.0.0.1');
        $this->assertFalse($isLocked, 'Failed attempts should be cleared after successful login');
    }

    /** @test */
    public function registration_requires_email_verification()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'timezone' => 'UTC',
        ]);

        $response->assertStatus(201);

        // User should exist but email_verified_at should be null
        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
    }
}
