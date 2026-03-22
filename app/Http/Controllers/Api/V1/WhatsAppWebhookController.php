<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AutomationRule;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappConnection;
use App\Services\AutomationService;
use App\Services\ConversationService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        protected WhatsAppService $whatsAppService,
        protected ConversationService $conversationService,
        protected AutomationService $automationService
    ) {}

    /**
     * Verify webhook (GET request from Meta).
     */
    public function verify(Request $request): mixed
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $result = $this->whatsAppService->verifyWebhookChallenge($mode, $token, $challenge);

        if ($result !== null) {
            return response($result, 200);
        }

        return response('Forbidden', 403);
    }

    /**
     * Handle incoming webhook (POST request from Meta).
     */
    public function handle(Request $request): JsonResponse
    {
        Log::info('📥 WhatsApp webhook received', [
            'ip' => $request->ip(),
            'content_length' => strlen($request->getContent()),
            'has_signature' => $request->hasHeader('X-Hub-Signature-256'),
        ]);

        // Verify signature
        $signature = $request->header('X-Hub-Signature-256', '');
        $payload = $request->getContent();

        if (!$this->whatsAppService->verifyWebhookSignature($payload, $signature)) {
            Log::warning('WhatsApp webhook signature verification failed', [
                'signature_preview' => substr($signature, 0, 20),
                'has_app_secret' => !empty(config('swiftfox.whatsapp.app_secret')),
            ]);
            return response()->json(['status' => 'error'], 401);
        }

        Log::info('✅ WhatsApp webhook signature verified');

        $data = $request->all();

        // Process the webhook
        try {
            $this->processWebhook($data);
        } catch (\Exception $e) {
            Log::error('WhatsApp webhook processing error', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }

        // Always return 200 to acknowledge receipt
        return response()->json(['status' => 'ok']);
    }

    /**
     * Process the webhook payload.
     */
    protected function processWebhook(array $data): void
    {
        Log::info('📋 Processing webhook data', [
            'has_entry' => isset($data['entry']),
            'entry_count' => count($data['entry'] ?? []),
        ]);

        // Check if this is a WhatsApp Business Account webhook
        if (!isset($data['entry'])) {
            return;
        }

        foreach ($data['entry'] as $entry) {
            $wabaId = $entry['id'] ?? null;

            if (!isset($entry['changes'])) {
                continue;
            }

            foreach ($entry['changes'] as $change) {
                if ($change['field'] !== 'messages') {
                    continue;
                }

                $value = $change['value'] ?? [];
                $this->processMessagesChange($wabaId, $value);
            }
        }
    }

    /**
     * Process messages change from webhook.
     */
    protected function processMessagesChange(?string $wabaId, array $value): void
    {
        $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

        if (!$phoneNumberId) {
            return;
        }

        // Find the account by phone_number_id
        $connection = WhatsappConnection::where('phone_number_id', $phoneNumberId)
            ->where('status', WhatsappConnection::STATUS_ACTIVE)
            ->first();

        if (!$connection) {
            Log::warning('WhatsApp webhook received for unknown phone_number_id', [
                'phone_number_id' => $phoneNumberId,
            ]);
            return;
        }

        $account = $connection->account;

        // Process incoming messages
        if (isset($value['messages'])) {
            foreach ($value['messages'] as $messageData) {
                $this->processIncomingMessage($account, $messageData, $value['contacts'] ?? []);
            }
        }

        // Process status updates
        if (isset($value['statuses'])) {
            foreach ($value['statuses'] as $statusData) {
                $this->processStatusUpdate($statusData);
            }
        }
    }

    /**
     * Process an incoming message.
     */
    protected function processIncomingMessage($account, array $messageData, array $contacts): void
    {
        $from = $messageData['from'] ?? null;
        $messageId = $messageData['id'] ?? null;
        $timestamp = $messageData['timestamp'] ?? null;
        $type = $messageData['type'] ?? 'text';

        if (!$from || !$messageId) {
            return;
        }

        // Get contact name from contacts array
        $contactName = null;
        foreach ($contacts as $contact) {
            if ($contact['wa_id'] === $from) {
                $contactName = $contact['profile']['name'] ?? null;
                break;
            }
        }

        // Get or create contact
        $contact = Contact::firstOrCreate(
            [
                'account_id' => $account->id,
                'phone_number' => '+' . $from,
            ],
            [
                'name' => $contactName ?? 'Unknown',
            ]
        );

        // Update contact name if we have a new one
        if ($contactName && $contact->name === 'Unknown') {
            $contact->update(['name' => $contactName]);
        }

        // Get or create conversation
        $conversation = $this->conversationService->getOrCreateConversation($contact);

        // Extract message content based on type
        $content = $this->extractMessageContent($messageData);

        // Check if message already exists (deduplication)
        if (Message::where('whatsapp_message_id', $messageId)->exists()) {
            return;
        }

        // Create the message
        $message = Message::create([
            'account_id' => $account->id,
            'conversation_id' => $conversation->id,
            'direction' => Message::DIRECTION_INBOUND,
            'content' => $content,
            'status' => Message::STATUS_DELIVERED,
            'whatsapp_message_id' => $messageId,
        ]);

        // Update conversation last_message_at
        $conversation->update(['last_message_at' => now()]);

        // Trigger automations for message_received
        try {
            $this->automationService->processTrigger(
                AutomationRule::TRIGGER_MESSAGE_RECEIVED,
                $conversation,
                $message
            );
        } catch (\Exception $e) {
            Log::error('Failed to process automations', [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract message content based on message type.
     */
    protected function extractMessageContent(array $messageData): string
    {
        $type = $messageData['type'] ?? 'text';

        switch ($type) {
            case 'text':
                return $messageData['text']['body'] ?? '';

            case 'image':
                return '[Image]' . ($messageData['image']['caption'] ?? '');

            case 'video':
                return '[Video]' . ($messageData['video']['caption'] ?? '');

            case 'audio':
                return '[Audio message]';

            case 'document':
                $filename = $messageData['document']['filename'] ?? 'document';
                return "[Document: {$filename}]";

            case 'sticker':
                return '[Sticker]';

            case 'location':
                $lat = $messageData['location']['latitude'] ?? '';
                $lng = $messageData['location']['longitude'] ?? '';
                return "[Location: {$lat}, {$lng}]";

            case 'contacts':
                return '[Contact shared]';

            case 'button':
                return $messageData['button']['text'] ?? '[Button response]';

            case 'interactive':
                return $messageData['interactive']['button_reply']['title']
                    ?? $messageData['interactive']['list_reply']['title']
                    ?? '[Interactive response]';

            default:
                return "[{$type} message]";
        }
    }

    /**
     * Process a message status update.
     */
    protected function processStatusUpdate(array $statusData): void
    {
        $messageId = $statusData['id'] ?? null;
        $status = $statusData['status'] ?? null;

        if (!$messageId || !$status) {
            return;
        }

        $message = Message::where('whatsapp_message_id', $messageId)->first();

        if (!$message) {
            return;
        }

        $statusMap = [
            'sent' => Message::STATUS_SENT,
            'delivered' => Message::STATUS_DELIVERED,
            'read' => Message::STATUS_READ,
            'failed' => Message::STATUS_FAILED,
        ];

        if (isset($statusMap[$status])) {
            $message->update(['status' => $statusMap[$status]]);
        }
    }
}
