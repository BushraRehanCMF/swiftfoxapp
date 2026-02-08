<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Conversation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'contact_id' => Contact::factory(),
            'assigned_user_id' => null,
            'status' => Conversation::STATUS_OPEN,
            'last_message_at' => now(),
            'conversation_started_at' => now(),
        ];
    }

    /**
     * Indicate that the conversation is closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Conversation::STATUS_CLOSED,
        ]);
    }

    /**
     * Indicate that the conversation is outside the messaging window.
     */
    public function outsideWindow(): static
    {
        return $this->state(fn (array $attributes) => [
            'conversation_started_at' => now()->subHours(25),
        ]);
    }
}
