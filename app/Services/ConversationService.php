<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

class ConversationService
{
    /**
     * Get or create a conversation for a contact.
     */
    public function getOrCreateConversation(Contact $contact): Conversation
    {
        // Look for an existing open conversation
        $conversation = Conversation::where('contact_id', $contact->id)
            ->where('status', Conversation::STATUS_OPEN)
            ->first();

        if ($conversation) {
            return $conversation;
        }

        // Create a new conversation
        return Conversation::create([
            'account_id' => $contact->account_id,
            'contact_id' => $contact->id,
            'status' => Conversation::STATUS_OPEN,
        ]);
    }

    /**
     * Process an incoming message from WhatsApp.
     */
    public function processIncomingMessage(
        Account $account,
        string $phoneNumber,
        string $content,
        string $whatsappMessageId,
        ?string $contactName = null
    ): Message {
        return DB::transaction(function () use ($account, $phoneNumber, $content, $whatsappMessageId, $contactName) {
            // Get or create contact
            $contact = Contact::firstOrCreate(
                [
                    'account_id' => $account->id,
                    'phone_number' => $phoneNumber,
                ],
                [
                    'name' => $contactName,
                ]
            );

            // Update contact name if provided and different
            if ($contactName && $contact->name !== $contactName) {
                $contact->update(['name' => $contactName]);
            }

            // Get or create conversation
            $conversation = $this->getOrCreateConversation($contact);

            // Check if this is a new conversation (starts 24h window)
            $isNewConversation = !$conversation->conversation_started_at ||
                !$conversation->isWithinMessagingWindow();

            if ($isNewConversation) {
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
                'direction' => Message::DIRECTION_INBOUND,
                'content' => $content,
                'status' => Message::STATUS_DELIVERED,
                'whatsapp_message_id' => $whatsappMessageId,
            ]);

            // Update conversation timestamp
            $conversation->update([
                'last_message_at' => now(),
                'status' => Conversation::STATUS_OPEN,
            ]);

            return $message;
        });
    }

    /**
     * Assign a user to a conversation.
     */
    public function assignUser(Conversation $conversation, ?\App\Models\User $user): Conversation
    {
        $conversation->update(['assigned_user_id' => $user?->id]);
        return $conversation->fresh();
    }

    /**
     * Close a conversation.
     */
    public function close(Conversation $conversation): Conversation
    {
        $conversation->update(['status' => Conversation::STATUS_CLOSED]);
        return $conversation->fresh();
    }

    /**
     * Reopen a conversation.
     */
    public function reopen(Conversation $conversation): Conversation
    {
        $conversation->update(['status' => Conversation::STATUS_OPEN]);
        return $conversation->fresh();
    }

    /**
     * Add labels to a conversation.
     */
    public function syncLabels(Conversation $conversation, array $labelIds): Conversation
    {
        $conversation->labels()->sync($labelIds);
        return $conversation->fresh(['labels']);
    }
}
