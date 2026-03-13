<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Account;
use App\Models\User;
use App\Models\WhatsappConnection;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WhatsAppConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $member;
    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create();
        $this->owner = User::factory()->owner()->create([
            'account_id' => $this->account->id,
        ]);
        $this->member = User::factory()->member()->create([
            'account_id' => $this->account->id,
        ]);
    }

    public function test_owner_can_get_whatsapp_status_when_not_connected(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/whatsapp/status');

        $response->assertOk()
            ->assertJsonPath('data.connected', false)
            ->assertJsonPath('data.phone_number', null);
    }

    public function test_owner_can_get_whatsapp_status_when_connected(): void
    {
        WhatsappConnection::factory()->create([
            'account_id' => $this->account->id,
            'phone_number' => '+1234567890',
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/whatsapp/status');

        $response->assertOk()
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.phone_number', '+1234567890');
    }

    public function test_owner_can_connect_whatsapp(): void
    {
        $mock = Mockery::mock(WhatsAppService::class);
        $mock->shouldReceive('exchangeCodeForWabaInfo')
            ->once()
            ->with('test_auth_code', false, 'https://example.com/whatsapp')
            ->andReturn([
                'waba_id' => '123456789012345',
                'phone_number_id' => '109876543210987',
                'phone_number' => '+1234567890',
                'access_token' => 'test_user_access_token',
            ]);

        $this->instance(WhatsAppService::class, $mock);

        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/whatsapp/connect', [
                'code' => 'test_auth_code',
                'redirect_uri' => 'https://example.com/whatsapp',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.phone_number', '+1234567890');

        $this->assertDatabaseHas('whatsapp_connections', [
            'account_id' => $this->account->id,
            'phone_number' => '+1234567890',
            'status' => 'active',
        ]);
    }

    public function test_cannot_connect_when_already_connected(): void
    {
        WhatsappConnection::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/whatsapp/connect', [
                'code' => 'test_auth_code',
                'redirect_uri' => 'https://example.com/whatsapp',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'ALREADY_CONNECTED');
    }

    public function test_owner_can_disconnect_whatsapp(): void
    {
        $connection = WhatsappConnection::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/whatsapp/disconnect');

        $response->assertOk()
            ->assertJsonPath('message', 'WhatsApp disconnected successfully.');

        $this->assertDatabaseHas('whatsapp_connections', [
            'id' => $connection->id,
            'status' => 'disconnected',
        ]);
    }

    public function test_owner_can_reconnect_when_connection_is_disconnected(): void
    {
        $connection = WhatsappConnection::factory()->create([
            'account_id' => $this->account->id,
            'waba_id' => 'old_waba',
            'phone_number_id' => 'old_phone_number_id',
            'phone_number' => '+10000000000',
            'status' => WhatsappConnection::STATUS_DISCONNECTED,
        ]);

        $mock = Mockery::mock(WhatsAppService::class);
        $mock->shouldReceive('exchangeAccessTokenForWabaInfo')
            ->once()
            ->with('test_access_token')
            ->andReturn([
                'waba_id' => 'new_waba',
                'phone_number_id' => 'new_phone_number_id',
                'phone_number' => '+19999999999',
                'access_token' => 'test_access_token',
            ]);

        $this->instance(WhatsAppService::class, $mock);

        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/whatsapp/connect', [
                'access_token' => 'test_access_token',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.waba_id', 'new_waba')
            ->assertJsonPath('data.phone_number_id', 'new_phone_number_id');

        $this->assertDatabaseHas('whatsapp_connections', [
            'id' => $connection->id,
            'account_id' => $this->account->id,
            'waba_id' => 'new_waba',
            'phone_number_id' => 'new_phone_number_id',
            'phone_number' => '+19999999999',
            'status' => WhatsappConnection::STATUS_ACTIVE,
        ]);
    }

    public function test_cannot_disconnect_when_not_connected(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/whatsapp/disconnect');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_CONNECTED');
    }

    public function test_member_cannot_access_whatsapp_routes(): void
    {
        $response = $this->actingAs($this->member)
            ->getJson('/api/v1/whatsapp/status');

        $response->assertStatus(403);
    }

    public function test_owner_can_get_embedded_signup_config(): void
    {
        config(['swiftfox.whatsapp.app_id' => 'test_app_id']);
        config(['swiftfox.whatsapp.config_id' => 'test_config_id']);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/whatsapp/config');

        $response->assertOk()
            ->assertJsonPath('data.app_id', 'test_app_id')
            ->assertJsonPath('data.config_id', 'test_config_id');
    }
}
