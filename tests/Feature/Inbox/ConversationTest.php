<?php

namespace Tests\Feature\Inbox;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Label;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationTest extends TestCase
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

    public function test_user_can_list_conversations(): void
    {
        $contact = Contact::factory()->create(['account_id' => $this->account->id]);
        $conversation = Conversation::factory()->create([
            'account_id' => $this->account->id,
            'contact_id' => $contact->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/conversations');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $conversation->id);
    }

    public function test_user_cannot_see_other_account_conversations(): void
    {
        $otherAccount = Account::factory()->create();
        $otherContact = Contact::factory()->create(['account_id' => $otherAccount->id]);
        Conversation::factory()->create([
            'account_id' => $otherAccount->id,
            'contact_id' => $otherContact->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/conversations');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_can_filter_by_status(): void
    {
        $contact = Contact::factory()->create(['account_id' => $this->account->id]);
        Conversation::factory()->create([
            'account_id' => $this->account->id,
            'contact_id' => $contact->id,
            'status' => Conversation::STATUS_OPEN,
        ]);
        Conversation::factory()->closed()->create([
            'account_id' => $this->account->id,
            'contact_id' => $contact->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/conversations?status=open');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_user_can_filter_by_assigned_user(): void
    {
        $contact = Contact::factory()->create(['account_id' => $this->account->id]);
        Conversation::factory()->create([
            'account_id' => $this->account->id,
            'contact_id' => $contact->id,
            'assigned_user_id' => $this->user->id,
        ]);
        Conversation::factory()->create([
            'account_id' => $this->account->id,
            'contact_id' => $contact->id,
            'assigned_user_id' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/conversations?assigned_user_id=' . $this->user->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_user_can_view_single_conversation(): void
    {
        $contact = Contact::factory()->create(['account_id' => $this->account->id]);
        $conversation = Conversation::factory()->create([
            'account_id' => $this->account->id,
            'contact_id' => $contact->id,
        ]);
        Message::factory()->count(3)->create([
            'account_id' => $this->account->id,
            'conversation_id' => $conversation->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/conversations/' . $conversation->id);

        $response->assertOk()
            ->assertJsonPath('data.id', $conversation->id)
            ->assertJsonCount(3, 'data.messages');
    }

    public function test_user_cannot_view_other_account_conversation(): void
    {
        $otherAccount = Account::factory()->create();
        $otherContact = Contact::factory()->create(['account_id' => $otherAccount->id]);
        $conversation = Conversation::factory()->create([
            'account_id' => $otherAccount->id,
            'contact_id' => $otherContact->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/conversations/' . $conversation->id);

        $response->assertNotFound();
    }

    public function test_user_can_get_paginated_messages(): void
    {
        $contact = Contact::factory()->create(['account_id' => $this->account->id]);
        $conversation = Conversation::factory()->create([
            'account_id' => $this->account->id,
            'contact_id' => $contact->id,
        ]);
        Message::factory()->count(5)->create([
            'account_id' => $this->account->id,
            'conversation_id' => $conversation->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/conversations/' . $conversation->id . '/messages');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_user_can_assign_conversation(): void
    {
        $contact = Contact::factory()->create(['account_id' => $this->account->id]);
        $conversation = Conversation::factory()->create([
            'account_id' => $this->account->id,
            'contact_id' => $contact->id,
        ]);
        $member = User::factory()->member()->create([
            'account_id' => $this->account->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/conversations/' . $conversation->id . '/assign', [
                'user_id' => $member->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.assigned_user.id', $member->id);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'assigned_user_id' => $member->id,
        ]);
    }

    public function test_user_cannot_assign_to_user_from_other_account(): void
    {
        $contact = Contact::factory()->create(['account_id' => $this->account->id]);
        $conversation = Conversation::factory()->create([
            'account_id' => $this->account->id,
            'contact_id' => $contact->id,
        ]);
        $otherAccount = Account::factory()->create();
        $otherUser = User::factory()->create([
            'account_id' => $otherAccount->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/conversations/' . $conversation->id . '/assign', [
                'user_id' => $otherUser->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_user_can_sync_labels(): void
    {
        $contact = Contact::factory()->create(['account_id' => $this->account->id]);
        $conversation = Conversation::factory()->create([
            'account_id' => $this->account->id,
            'contact_id' => $contact->id,
        ]);
        $labels = Label::factory()->count(2)->create([
            'account_id' => $this->account->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/conversations/' . $conversation->id . '/labels', [
                'label_ids' => $labels->pluck('id')->toArray(),
            ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.labels');
    }

    public function test_user_can_close_conversation(): void
    {
        $contact = Contact::factory()->create(['account_id' => $this->account->id]);
        $conversation = Conversation::factory()->create([
            'account_id' => $this->account->id,
            'contact_id' => $contact->id,
            'status' => Conversation::STATUS_OPEN,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/conversations/' . $conversation->id . '/close');

        $response->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'status' => Conversation::STATUS_CLOSED,
        ]);
    }

    public function test_user_can_reopen_conversation(): void
    {
        $contact = Contact::factory()->create(['account_id' => $this->account->id]);
        $conversation = Conversation::factory()->closed()->create([
            'account_id' => $this->account->id,
            'contact_id' => $contact->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/conversations/' . $conversation->id . '/reopen');

        $response->assertOk()
            ->assertJsonPath('data.status', 'open');

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'status' => Conversation::STATUS_OPEN,
        ]);
    }

    public function test_user_can_search_conversations(): void
    {
        $contact1 = Contact::factory()->create([
            'account_id' => $this->account->id,
            'name' => 'John Doe',
        ]);
        $contact2 = Contact::factory()->create([
            'account_id' => $this->account->id,
            'name' => 'Jane Smith',
        ]);
        Conversation::factory()->create([
            'account_id' => $this->account->id,
            'contact_id' => $contact1->id,
        ]);
        Conversation::factory()->create([
            'account_id' => $this->account->id,
            'contact_id' => $contact2->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/conversations?search=John');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.contact.name', 'John Doe');
    }
}
