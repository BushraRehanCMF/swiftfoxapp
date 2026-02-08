<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AutomationRule;
use App\Models\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $member;
    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create();
        $this->owner = User::factory()->create([
            'account_id' => $this->account->id,
            'role' => 'owner',
        ]);
        $this->member = User::factory()->create([
            'account_id' => $this->account->id,
            'role' => 'member',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization Tests
    |--------------------------------------------------------------------------
    */

    public function test_owner_can_access_automations(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/automations');

        $response->assertOk();
    }

    public function test_member_cannot_access_automations(): void
    {
        $response = $this->actingAs($this->member)
            ->getJson('/api/v1/automations');

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_automations(): void
    {
        $response = $this->getJson('/api/v1/automations');

        $response->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | List Automations Tests
    |--------------------------------------------------------------------------
    */

    public function test_owner_can_list_automations(): void
    {
        AutomationRule::factory()->count(3)->create([
            'account_id' => $this->account->id,
        ]);

        // Create automation for different account (should not appear)
        $otherAccount = Account::factory()->create();
        AutomationRule::factory()->create([
            'account_id' => $otherAccount->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/automations');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_automations_are_ordered_by_name(): void
    {
        AutomationRule::factory()->create([
            'account_id' => $this->account->id,
            'name' => 'Zulu Rule',
        ]);
        AutomationRule::factory()->create([
            'account_id' => $this->account->id,
            'name' => 'Alpha Rule',
        ]);
        AutomationRule::factory()->create([
            'account_id' => $this->account->id,
            'name' => 'Beta Rule',
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/automations');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('Alpha Rule', $data[0]['name']);
        $this->assertEquals('Beta Rule', $data[1]['name']);
        $this->assertEquals('Zulu Rule', $data[2]['name']);
    }

    /*
    |--------------------------------------------------------------------------
    | Create Automation Tests
    |--------------------------------------------------------------------------
    */

    public function test_owner_can_create_automation(): void
    {
        $label = Label::factory()->create(['account_id' => $this->account->id]);

        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/automations', [
                'name' => 'Test Automation',
                'trigger_type' => 'message_received',
                'conditions' => [
                    [
                        'field' => 'message.content',
                        'operator' => 'contains',
                        'value' => 'help',
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'add_label',
                        'value' => $label->id,
                    ],
                ],
                'is_enabled' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Test Automation')
            ->assertJsonPath('data.trigger_type', 'message_received')
            ->assertJsonPath('data.is_enabled', true)
            ->assertJsonPath('message', 'Automation rule created successfully.');

        $this->assertDatabaseHas('automation_rules', [
            'name' => 'Test Automation',
            'account_id' => $this->account->id,
        ]);
    }

    public function test_automation_requires_name(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/automations', [
                'trigger_type' => 'message_received',
                'actions' => [
                    ['type' => 'assign_user', 'value' => 1],
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_automation_requires_valid_trigger_type(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/automations', [
                'name' => 'Test',
                'trigger_type' => 'invalid_trigger',
                'actions' => [
                    ['type' => 'assign_user', 'value' => 1],
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['trigger_type']);
    }

    public function test_automation_requires_at_least_one_action(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/automations', [
                'name' => 'Test',
                'trigger_type' => 'message_received',
                'actions' => [],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['actions']);
    }

    public function test_automation_requires_valid_action_type(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/automations', [
                'name' => 'Test',
                'trigger_type' => 'message_received',
                'actions' => [
                    ['type' => 'invalid_action', 'value' => 'test'],
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['actions.0.type']);
    }

    public function test_automation_can_be_created_without_conditions(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/automations', [
                'name' => 'No Conditions',
                'trigger_type' => 'message_received',
                'actions' => [
                    ['type' => 'assign_user', 'value' => $this->member->id],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.conditions', null);
    }

    /*
    |--------------------------------------------------------------------------
    | Show Automation Tests
    |--------------------------------------------------------------------------
    */

    public function test_owner_can_view_automation(): void
    {
        $automation = AutomationRule::factory()->create([
            'account_id' => $this->account->id,
            'name' => 'Test Rule',
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/automations/{$automation->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Test Rule');
    }

    public function test_owner_cannot_view_other_account_automation(): void
    {
        $otherAccount = Account::factory()->create();
        $automation = AutomationRule::factory()->create([
            'account_id' => $otherAccount->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/automations/{$automation->id}");

        $response->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Update Automation Tests
    |--------------------------------------------------------------------------
    */

    public function test_owner_can_update_automation(): void
    {
        $automation = AutomationRule::factory()->create([
            'account_id' => $this->account->id,
            'name' => 'Old Name',
        ]);

        $response = $this->actingAs($this->owner)
            ->putJson("/api/v1/automations/{$automation->id}", [
                'name' => 'New Name',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('message', 'Automation rule updated successfully.');

        $this->assertDatabaseHas('automation_rules', [
            'id' => $automation->id,
            'name' => 'New Name',
        ]);
    }

    public function test_owner_can_update_automation_trigger(): void
    {
        $automation = AutomationRule::factory()->create([
            'account_id' => $this->account->id,
            'trigger_type' => 'message_received',
        ]);

        $response = $this->actingAs($this->owner)
            ->putJson("/api/v1/automations/{$automation->id}", [
                'trigger_type' => 'conversation_opened',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.trigger_type', 'conversation_opened');
    }

    public function test_owner_cannot_update_other_account_automation(): void
    {
        $otherAccount = Account::factory()->create();
        $automation = AutomationRule::factory()->create([
            'account_id' => $otherAccount->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->putJson("/api/v1/automations/{$automation->id}", [
                'name' => 'Hacked',
            ]);

        $response->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Automation Tests
    |--------------------------------------------------------------------------
    */

    public function test_owner_can_delete_automation(): void
    {
        $automation = AutomationRule::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/v1/automations/{$automation->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Automation rule deleted successfully.');

        $this->assertDatabaseMissing('automation_rules', [
            'id' => $automation->id,
        ]);
    }

    public function test_owner_cannot_delete_other_account_automation(): void
    {
        $otherAccount = Account::factory()->create();
        $automation = AutomationRule::factory()->create([
            'account_id' => $otherAccount->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/v1/automations/{$automation->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('automation_rules', ['id' => $automation->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Toggle Automation Tests
    |--------------------------------------------------------------------------
    */

    public function test_owner_can_enable_automation(): void
    {
        $automation = AutomationRule::factory()->create([
            'account_id' => $this->account->id,
            'is_enabled' => false,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/automations/{$automation->id}/toggle");

        $response->assertOk()
            ->assertJsonPath('data.is_enabled', true);
    }

    public function test_owner_can_disable_automation(): void
    {
        $automation = AutomationRule::factory()->create([
            'account_id' => $this->account->id,
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/automations/{$automation->id}/toggle");

        $response->assertOk()
            ->assertJsonPath('data.is_enabled', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Triggers & Actions Endpoints
    |--------------------------------------------------------------------------
    */

    public function test_owner_can_get_available_triggers(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/automations/triggers');

        $response->assertOk()
            ->assertJsonCount(3, 'data');

        $triggers = collect($response->json('data'));
        $this->assertTrue($triggers->contains('value', 'message_received'));
        $this->assertTrue($triggers->contains('value', 'conversation_opened'));
        $this->assertTrue($triggers->contains('value', 'keyword_matched'));
    }

    public function test_owner_can_get_available_actions(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/automations/actions');

        $response->assertOk()
            ->assertJsonCount(3, 'data');

        $actions = collect($response->json('data'));
        $this->assertTrue($actions->contains('value', 'assign_user'));
        $this->assertTrue($actions->contains('value', 'add_label'));
        $this->assertTrue($actions->contains('value', 'send_reply'));
    }
}
