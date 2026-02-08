<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_login_with_valid_credentials(): void
    {
        $account = Account::factory()->create();
        $user = User::factory()->forAccount($account)->owner()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
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
    }

    public function test_users_cannot_login_with_invalid_credentials(): void
    {
        $account = Account::factory()->create();
        User::factory()->forAccount($account)->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_users_can_logout(): void
    {
        $account = Account::factory()->create();
        $user = User::factory()->forAccount($account)->create();

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully.']);

        // Token should be revoked
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_authenticated_user_can_get_their_info(): void
    {
        $account = Account::factory()->create();
        $user = User::factory()->forAccount($account)->owner()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/auth/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'account' => [
                        'id',
                        'name',
                        'subscription_status',
                        'trial',
                        'usage',
                    ],
                ],
            ]);
    }

    public function test_super_admin_can_login(): void
    {
        $user = User::factory()->superAdmin()->create([
            'email' => 'admin@swiftfox.cloud',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@swiftfox.cloud',
            'password' => 'password',
        ]);

        $response->assertStatus(200);

        // Super admin should not have account in response
        $this->assertNull($response->json('data.user.account'));
    }
}
