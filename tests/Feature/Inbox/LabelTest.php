<?php

namespace Tests\Feature\Inbox;

use App\Models\Account;
use App\Models\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create();
        $this->user = User::factory()->owner()->create([
            'account_id' => $this->account->id,
        ]);
    }

    public function test_user_can_list_labels(): void
    {
        Label::factory()->count(3)->create(['account_id' => $this->account->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/labels');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_user_cannot_see_other_account_labels(): void
    {
        $otherAccount = Account::factory()->create();
        Label::factory()->create(['account_id' => $otherAccount->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/labels');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_can_create_label(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/labels', [
                'name' => 'Important',
                'color' => '#EF4444',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Important')
            ->assertJsonPath('data.color', '#EF4444');

        $this->assertDatabaseHas('labels', [
            'account_id' => $this->account->id,
            'name' => 'Important',
        ]);
    }

    public function test_label_requires_valid_color(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/labels', [
                'name' => 'Invalid',
                'color' => 'red', // Invalid format
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['color']);
    }

    public function test_user_can_update_label(): void
    {
        $label = Label::factory()->create(['account_id' => $this->account->id]);

        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/labels/' . $label->id, [
                'name' => 'Updated Name',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_user_can_delete_label(): void
    {
        $label = Label::factory()->create(['account_id' => $this->account->id]);

        $response = $this->actingAs($this->user)
            ->deleteJson('/api/v1/labels/' . $label->id);

        $response->assertOk();

        $this->assertDatabaseMissing('labels', [
            'id' => $label->id,
        ]);
    }

    public function test_user_cannot_delete_other_account_label(): void
    {
        $otherAccount = Account::factory()->create();
        $label = Label::factory()->create(['account_id' => $otherAccount->id]);

        $response = $this->actingAs($this->user)
            ->deleteJson('/api/v1/labels/' . $label->id);

        $response->assertNotFound();
    }
}
