<?php

namespace Tests\Feature\Inbox;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Account $account;
    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create([
            'conversations_used' => 0,
            'conversations_limit' => 100,
        ]);
        $this->user = User::factory()->owner()->create([
            'account_id' => $this->account->id,
        ]);

        $contact = Contact::factory()->create(['account_id' => $this->account->id]);
        $this->conversation = Conversation::factory()->create([
            'account_id' => $this->account->id,
            'contact_id' => $contact->id,
        ]);

        // Create WhatsApp connection for the account
        WhatsappConnection::factory()->create([
            'account_id' => $this->account->id,
        ]);
    }

    public function test_user_can_send_message(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/conversations/' . $this->conversation->id . '/messages', [
                'content' => 'Hello, this is a test message!',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.content', 'Hello, this is a test message!')
            ->assertJsonPath('data.direction', Message::DIRECTION_OUTBOUND);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->conversation->id,
            'content' => 'Hello, this is a test message!',
            'direction' => Message::DIRECTION_OUTBOUND,
        ]);
    }

    public function test_message_content_is_required(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/conversations/' . $this->conversation->id . '/messages', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_user_cannot_send_message_when_trial_expired(): void
    {
        $this->account->update([
            'subscription_status' => Account::STATUS_EXPIRED,
            'trial_ends_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/conversations/' . $this->conversation->id . '/messages', [
                'content' => 'This should fail',
            ]);

        $response->assertStatus(402)
            ->assertJsonPath('error.code', 'SUBSCRIPTION_REQUIRED');
    }

    public function test_user_cannot_send_message_when_limit_reached(): void
    {
        $this->account->update([
            'conversations_used' => 100,
            'conversations_limit' => 100,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/conversations/' . $this->conversation->id . '/messages', [
                'content' => 'This should fail',
            ]);

        $response->assertStatus(402)
            ->assertJsonPath('error.code', 'CONVERSATION_LIMIT_REACHED');
    }

    public function test_user_cannot_send_message_without_whatsapp_connection(): void
    {
        WhatsappConnection::where('account_id', $this->account->id)->delete();

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/conversations/' . $this->conversation->id . '/messages', [
                'content' => 'This should fail',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'WHATSAPP_NOT_CONNECTED');
    }

    public function test_user_cannot_send_message_to_other_account_conversation(): void
    {
        $otherAccount = Account::factory()->create();
        $otherContact = Contact::factory()->create(['account_id' => $otherAccount->id]);
        $otherConversation = Conversation::factory()->create([
            'account_id' => $otherAccount->id,
            'contact_id' => $otherContact->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/conversations/' . $otherConversation->id . '/messages', [
                'content' => 'This should fail',
            ]);

        $response->assertNotFound();
    }
}
