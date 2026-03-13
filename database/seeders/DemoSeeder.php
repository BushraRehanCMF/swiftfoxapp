<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AutomationRule;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Label;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsappConnection;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Seed demo data for the demo account.
     */
    public function run(): void
    {
        // Get the demo account
        $demoUser = User::where('email', 'demo@swiftfox.cloud')->first();
        if (!$demoUser || !$demoUser->account_id) {
            $this->command->error('Demo account not found. Run DatabaseSeeder first.');
            return;
        }

        $account = Account::find($demoUser->account_id);

        $this->command->info('Seeding demo data for account: ' . $account->name);

        // Create a fake WhatsApp connection
        $this->seedWhatsappConnection($account);

        // Create demo labels
        $labels = $this->seedLabels($account);

        // Create demo contacts and conversations
        $this->seedContactsAndConversations($account, $labels);

        // Get the demo member user
        $memberUser = User::where('email', 'member@swiftfox.cloud')->first();

        // Create demo automations
        $this->seedAutomations($account, $memberUser);

        $this->command->info('✓ WhatsApp connection created (fake)');
        $this->command->info('✓ 8 demo contacts created');
        $this->command->info('✓ 8 demo conversations created');
        $this->command->info('✓ 40+ demo messages created');
        $this->command->info('✓ 4 demo labels created');
        $this->command->info('✓ 2 demo automation rules created');
        $this->command->info('');
        $this->command->info('Demo is ready! Login with demo@swiftfox.cloud / password');
    }

    private function seedWhatsappConnection(Account $account): void
    {
        WhatsappConnection::create([
            'account_id' => $account->id,
            'waba_id' => '123456789012345',
            'phone_number_id' => '987654321098765',
            'phone_number' => '+1 (555) 123-4567',
            'access_token' => 'demo_access_token',
            'status' => WhatsappConnection::STATUS_ACTIVE,
        ]);
    }

    private function seedLabels(Account $account): array
    {
        return [
            Label::create([
                'account_id' => $account->id,
                'name' => 'High Priority',
                'color' => '#ef4444', // red
            ]),
            Label::create([
                'account_id' => $account->id,
                'name' => 'Follow Up',
                'color' => '#f59e0b', // amber
            ]),
            Label::create([
                'account_id' => $account->id,
                'name' => 'Resolved',
                'color' => '#10b981', // emerald
            ]),
            Label::create([
                'account_id' => $account->id,
                'name' => 'Support',
                'color' => '#3b82f6', // blue
            ]),
        ];
    }

    private function seedContactsAndConversations(Account $account, array $labels): void
    {
        $demoContacts = [
            [
                'name' => 'Sarah Johnson',
                'phone' => '+1 (555) 111-2222',
                'messages' => [
                    ['direction' => Message::DIRECTION_INBOUND, 'text' => 'Hi! I\'m interested in your product. Can you tell me more?'],
                    ['direction' => Message::DIRECTION_OUTBOUND, 'text' => 'Hello Sarah! I\'d be happy to help. What would you like to know?'],
                    ['direction' => Message::DIRECTION_INBOUND, 'text' => 'Do you have pricing information?'],
                    ['direction' => Message::DIRECTION_OUTBOUND, 'text' => 'Yes! We have flexible plans starting at $29/month. Would you like more details?'],
                ],
                'label_id' => $labels[3]->id, // Support
            ],
            [
                'name' => 'Mike Chen',
                'phone' => '+1 (555) 222-3333',
                'messages' => [
                    ['direction' => Message::DIRECTION_INBOUND, 'text' => 'I need help with my account'],
                    ['direction' => Message::DIRECTION_OUTBOUND, 'text' => 'Sure! What seems to be the issue?'],
                    ['direction' => Message::DIRECTION_INBOUND, 'text' => 'I can\'t log in to my account'],
                    ['direction' => Message::DIRECTION_OUTBOUND, 'text' => 'Let me help you reset your password. Check your email shortly.'],
                    ['direction' => Message::DIRECTION_INBOUND, 'text' => 'Got it! Thanks so much 😊'],
                ],
                'label_id' => $labels[2]->id, // Resolved
            ],
            [
                'name' => 'Emily Rodriguez',
                'phone' => '+1 (555) 333-4444',
                'messages' => [
                    ['direction' => Message::DIRECTION_INBOUND, 'text' => 'Quick question about the new feature'],
                    ['direction' => Message::DIRECTION_OUTBOUND, 'text' => 'Hi Emily! Happy to help. What\'s your question?'],
                    ['direction' => Message::DIRECTION_INBOUND, 'text' => 'When will advanced reporting be available?'],
                ],
                'label_id' => $labels[1]->id, // Follow Up
            ],
            [
                'name' => 'David Martinez',
                'phone' => '+1 (555) 444-5555',
                'messages' => [
                    ['direction' => Message::DIRECTION_INBOUND, 'text' => 'URGENT: System is down!'],
                    ['direction' => Message::DIRECTION_OUTBOUND, 'text' => 'We\'re investigating this immediately. ETA 15 minutes.'],
                    ['direction' => Message::DIRECTION_OUTBOUND, 'text' => 'System is back online now. Sorry for the inconvenience!'],
                    ['direction' => Message::DIRECTION_INBOUND, 'text' => 'Thanks for the quick fix!'],
                ],
                'label_id' => $labels[0]->id, // High Priority
            ],
            [
                'name' => 'Lisa Wong',
                'phone' => '+1 (555) 555-6666',
                'messages' => [
                    ['direction' => Message::DIRECTION_INBOUND, 'text' => 'Love your product! Any discounts for annual plans?'],
                    ['direction' => Message::DIRECTION_OUTBOUND, 'text' => 'Thanks Lisa! Yes, we offer 20% off annual subscriptions.'],
                    ['direction' => Message::DIRECTION_INBOUND, 'text' => 'Perfect! Let\'s go with annual.'],
                ],
                'label_id' => $labels[2]->id, // Resolved
            ],
            [
                'name' => 'James Wilson',
                'phone' => '+1 (555) 666-7777',
                'messages' => [
                    ['direction' => Message::DIRECTION_INBOUND, 'text' => 'Is there a free trial available?'],
                    ['direction' => Message::DIRECTION_OUTBOUND, 'text' => 'Yes! We offer a 14-day free trial with full access to all features.'],
                    ['direction' => Message::DIRECTION_INBOUND, 'text' => 'Great, signing up now!'],
                ],
                'label_id' => null,
            ],
            [
                'name' => 'Amanda Foster',
                'phone' => '+1 (555) 777-8888',
                'messages' => [
                    ['direction' => Message::DIRECTION_INBOUND, 'text' => 'Does it integrate with Slack?'],
                    ['direction' => Message::DIRECTION_OUTBOUND, 'text' => 'We currently support email and webhook integrations. Slack is on our roadmap!'],
                ],
                'label_id' => $labels[1]->id, // Follow Up
            ],
            [
                'name' => 'Robert Kim',
                'phone' => '+1 (555) 888-9999',
                'messages' => [
                    ['direction' => Message::DIRECTION_INBOUND, 'text' => 'Your team is amazing! Best customer service ever!'],
                    ['direction' => Message::DIRECTION_OUTBOUND, 'text' => 'Thank you so much, Robert! We appreciate you! 🙏'],
                    ['direction' => Message::DIRECTION_INBOUND, 'text' => 'Just wanted to let you know 💙'],
                ],
                'label_id' => $labels[2]->id, // Resolved
            ],
        ];

        foreach ($demoContacts as $contactData) {
            $contact = Contact::create([
                'account_id' => $account->id,
                'phone_number' => $contactData['phone'],
                'name' => $contactData['name'],
            ]);

            $conversation = Conversation::create([
                'account_id' => $account->id,
                'contact_id' => $contact->id,
                'status' => Conversation::STATUS_OPEN,
                'last_message_at' => now()->subHours(rand(1, 48)),
                'conversation_started_at' => now()->subHours(rand(2, 96)),
            ]);

            // Add messages
            foreach ($contactData['messages'] as $index => $messageData) {
                Message::create([
                    'account_id' => $account->id,
                    'conversation_id' => $conversation->id,
                    'direction' => $messageData['direction'],
                    'content' => $messageData['text'],
                    'status' => Message::STATUS_DELIVERED,
                    'whatsapp_message_id' => 'wamid_' . uniqid(),
                    'created_at' => now()->subHours(rand(1, 48) - $index),
                ]);
            }

            // Add label if provided
            if ($contactData['label_id']) {
                $conversation->labels()->attach($contactData['label_id']);
            }
        }
    }

    private function seedAutomations(Account $account, ?User $memberUser): void
    {
        // Auto-reply automation
        AutomationRule::create([
            'account_id' => $account->id,
            'name' => 'Welcome Message',
            'trigger_type' => AutomationRule::TRIGGER_CONVERSATION_OPENED,
            'conditions' => json_encode([
                'type' => 'conversation_opened',
            ]),
            'actions' => json_encode([
                [
                    'type' => 'send_reply',
                    'message' => 'Thanks for reaching out! We\'ll get back to you shortly during business hours.',
                ],
                [
                    'type' => 'add_label',
                    'label_name' => 'Support',
                ],
            ]),
            'is_enabled' => true,
        ]);

        // Assignment automation
        if ($memberUser) {
            AutomationRule::create([
                'account_id' => $account->id,
                'name' => 'Auto-assign to Member',
                'trigger_type' => AutomationRule::TRIGGER_CONVERSATION_OPENED,
                'conditions' => json_encode([
                    'type' => 'conversation_opened',
                ]),
                'actions' => json_encode([
                    [
                        'type' => 'assign_user',
                        'user_id' => $memberUser->id,
                    ],
                ]),
                'is_enabled' => true,
            ]);
        }
    }
}
