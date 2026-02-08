<?php

namespace Tests\Feature\Inbox;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTest extends TestCase
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

    public function test_user_can_list_contacts(): void
    {
        Contact::factory()->count(3)->create(['account_id' => $this->account->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/contacts');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_user_cannot_see_other_account_contacts(): void
    {
        $otherAccount = Account::factory()->create();
        Contact::factory()->create(['account_id' => $otherAccount->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/contacts');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_can_view_single_contact(): void
    {
        $contact = Contact::factory()->create(['account_id' => $this->account->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/contacts/' . $contact->id);

        $response->assertOk()
            ->assertJsonPath('data.id', $contact->id);
    }

    public function test_user_can_update_contact(): void
    {
        $contact = Contact::factory()->create([
            'account_id' => $this->account->id,
            'name' => 'Original Name',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/contacts/' . $contact->id, [
                'name' => 'Updated Name',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_user_can_sync_labels_to_contact(): void
    {
        $contact = Contact::factory()->create(['account_id' => $this->account->id]);
        $labels = Label::factory()->count(2)->create(['account_id' => $this->account->id]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/contacts/' . $contact->id . '/labels', [
                'label_ids' => $labels->pluck('id')->toArray(),
            ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.labels');
    }

    public function test_user_can_get_contact_conversations(): void
    {
        $contact = Contact::factory()->create(['account_id' => $this->account->id]);
        Conversation::factory()->count(2)->create([
            'account_id' => $this->account->id,
            'contact_id' => $contact->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/contacts/' . $contact->id . '/conversations');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_search_contacts(): void
    {
        Contact::factory()->create([
            'account_id' => $this->account->id,
            'name' => 'John Doe',
            'phone_number' => '+1234567890',
        ]);
        Contact::factory()->create([
            'account_id' => $this->account->id,
            'name' => 'Jane Smith',
            'phone_number' => '+0987654321',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/contacts?search=John');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'John Doe');
    }

    public function test_user_can_filter_contacts_by_label(): void
    {
        $label = Label::factory()->create(['account_id' => $this->account->id]);
        $contactWithLabel = Contact::factory()->create(['account_id' => $this->account->id]);
        $contactWithLabel->labels()->attach($label);
        Contact::factory()->create(['account_id' => $this->account->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/contacts?label_id=' . $label->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
