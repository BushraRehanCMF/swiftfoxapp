<?php

namespace App\Services;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Account;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

class MessageService
{
    public function __construct(
        protected ConversationService $conversationService
    ) {}

    /**
     * Send a message in a conversation.
     *
     * @throws \Exception
     */
    public function sendMessage(Conversation $conversation, string $content): Message
    {
        $account = $conversation->account;

        // Validate account can send messages
        $validation = $this->validateCanSend($account, $conversation);
        if (!$validation['can_send']) {
            throw new \Exception($validation['reason']);
        }

        return DB::transaction(function () use ($conversation, $content, $account) {
            // Check if this starts a new 24h window (user-initiated)
            $isNewConversation = !$conversation->conversation_started_at ||
                !$conversation->isWithinMessagingWindow();

            if ($isNewConversation) {
                // This is a user-initiated conversation (template message would be required)
                // For now, we'll track it but in production, you'd need template handling
                $conversation->update([
                    'conversation_started_at' => now(),
                ]);

                // Increment conversation count for billing
                $account->incrementConversationCount();
            }

            // Create the message
            $message = Message::create([
                'account_id' => $account->id,
                'conversation_id' => $conversation->id,
                'direction' => Message::DIRECTION_OUTBOUND,
                'content' => $content,
                'status' => Message::STATUS_PENDING,
            ]);

            // Update conversation timestamp
            $conversation->update([
                'last_message_at' => now(),
            ]);

            // Dispatch job to send via WhatsApp API
            SendWhatsAppMessage::dispatch($message);

            return $message;
        });
    }

    /**
     * Validate that the account can send messages.
     *
     * @return array{can_send: bool, reason: string|null}
     */
    public function validateCanSend(Account $account, ?Conversation $conversation = null): array
    {
        // Check subscription/trial status
        if (!$account->hasActiveSubscription() && !$account->isOnTrial()) {
            return [
                'can_send' => false,
                'reason' => 'Your trial has expired. Please upgrade to send messages.',
            ];
        }

        // Check conversation limit
        if ($account->conversations_used >= $account->conversations_limit) {
            // Allow if within existing 24h window
            if ($conversation && !$conversation->isWithinMessagingWindow()) {
                return [
                    'can_send' => false,
                    'reason' => 'You have reached your conversation limit. Please upgrade to continue.',
                ];
            }
            if (!$conversation) {
                return [
                    'can_send' => false,
                    'reason' => 'You have reached your conversation limit. Please upgrade to continue.',
                ];
            }
        }

        // Check WhatsApp connection
        if (!$account->hasWhatsappConnected()) {
            return [
                'can_send' => false,
                'reason' => 'Please connect your WhatsApp Business number first.',
            ];
        }

        return ['can_send' => true, 'reason' => null];
    }

    /**
     * Update message status (called by webhook).
     */
    public function updateMessageStatus(string $whatsappMessageId, string $status): ?Message
    {
        $message = Message::where('whatsapp_message_id', $whatsappMessageId)->first();

        if (!$message) {
            return null;
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

        return $message->fresh();
    }
}
