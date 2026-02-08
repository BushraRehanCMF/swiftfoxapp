<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected Account $account;
    protected WhatsappConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        config(['swiftfox.whatsapp.webhook_verify_token' => 'test_verify_token']);
        config(['swiftfox.whatsapp.app_secret' => 'test_app_secret']);

        $this->account = Account::factory()->create();
        $this->connection = WhatsappConnection::factory()->create([
            'account_id' => $this->account->id,
            'phone_number_id' => '109876543210987',
        ]);
    }

    public function test_webhook_verification_succeeds(): void
    {
        $response = $this->getJson('/api/v1/webhooks/whatsapp?' . http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'test_verify_token',
            'hub_challenge' => 'test_challenge_string',
        ]));

        $response->assertOk();
        $this->assertEquals('test_challenge_string', $response->getContent());
    }

    public function test_webhook_verification_fails_with_wrong_token(): void
    {
        $response = $this->getJson('/api/v1/webhooks/whatsapp?' . http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'wrong_token',
            'hub_challenge' => 'test_challenge_string',
        ]));

        $response->assertStatus(403);
    }

    public function test_incoming_message_creates_contact_and_message(): void
    {
        $payload = $this->buildIncomingMessagePayload(
            phoneNumberId: '109876543210987',
            from: '1234567890',
            messageId: 'wamid_test123',
            content: 'Hello from WhatsApp!'
        );

        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_app_secret');

        $response = $this->postJson('/api/v1/webhooks/whatsapp', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertOk();

        // Check contact was created
        $this->assertDatabaseHas('contacts', [
            'account_id' => $this->account->id,
            'phone_number' => '+1234567890',
        ]);

        // Check message was created
        $this->assertDatabaseHas('messages', [
            'account_id' => $this->account->id,
            'content' => 'Hello from WhatsApp!',
            'direction' => 'inbound',
            'whatsapp_message_id' => 'wamid_test123',
        ]);
    }

    public function test_duplicate_messages_are_not_created(): void
    {
        // Create existing message
        $contact = Contact::factory()->create(['account_id' => $this->account->id]);
        $conversation = Conversation::factory()->create([
            'account_id' => $this->account->id,
            'contact_id' => $contact->id,
        ]);
        Message::factory()->create([
            'account_id' => $this->account->id,
            'conversation_id' => $conversation->id,
            'whatsapp_message_id' => 'wamid_duplicate',
        ]);

        $payload = $this->buildIncomingMessagePayload(
            phoneNumberId: '109876543210987',
            from: '1234567890',
            messageId: 'wamid_duplicate',
            content: 'Duplicate message'
        );

        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_app_secret');

        $this->postJson('/api/v1/webhooks/whatsapp', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        // Should still only have 1 message with this ID
        $this->assertEquals(1, Message::where('whatsapp_message_id', 'wamid_duplicate')->count());
    }

    public function test_status_update_changes_message_status(): void
    {
        $contact = Contact::factory()->create(['account_id' => $this->account->id]);
        $conversation = Conversation::factory()->create([
            'account_id' => $this->account->id,
            'contact_id' => $contact->id,
        ]);
        $message = Message::factory()->create([
            'account_id' => $this->account->id,
            'conversation_id' => $conversation->id,
            'whatsapp_message_id' => 'wamid_status_test',
            'status' => Message::STATUS_SENT,
        ]);

        $payload = $this->buildStatusUpdatePayload(
            phoneNumberId: '109876543210987',
            messageId: 'wamid_status_test',
            status: 'delivered'
        );

        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test_app_secret');

        $this->postJson('/api/v1/webhooks/whatsapp', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'status' => Message::STATUS_DELIVERED,
        ]);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $payload = $this->buildIncomingMessagePayload(
            phoneNumberId: '109876543210987',
            from: '1234567890',
            messageId: 'wamid_test',
            content: 'Test'
        );

        $response = $this->postJson('/api/v1/webhooks/whatsapp', $payload, [
            'X-Hub-Signature-256' => 'sha256=invalid_signature',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Build a sample incoming message webhook payload.
     */
    protected function buildIncomingMessagePayload(
        string $phoneNumberId,
        string $from,
        string $messageId,
        string $content
    ): array {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123456789',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '15551234567',
                                    'phone_number_id' => $phoneNumberId,
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Test User'],
                                        'wa_id' => $from,
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => $from,
                                        'id' => $messageId,
                                        'timestamp' => (string) time(),
                                        'type' => 'text',
                                        'text' => ['body' => $content],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build a sample status update webhook payload.
     */
    protected function buildStatusUpdatePayload(
        string $phoneNumberId,
        string $messageId,
        string $status
    ): array {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123456789',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '15551234567',
                                    'phone_number_id' => $phoneNumberId,
                                ],
                                'statuses' => [
                                    [
                                        'id' => $messageId,
                                        'status' => $status,
                                        'timestamp' => (string) time(),
                                        'recipient_id' => '1234567890',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
