<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'conversation_id' => Conversation::factory(),
            'direction' => fake()->randomElement([Message::DIRECTION_INBOUND, Message::DIRECTION_OUTBOUND]),
            'content' => fake()->sentence(),
            'status' => Message::STATUS_DELIVERED,
            'whatsapp_message_id' => 'wamid_' . fake()->uuid(),
        ];
    }

    /**
     * Indicate that the message is inbound.
     */
    public function inbound(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => Message::DIRECTION_INBOUND,
        ]);
    }

    /**
     * Indicate that the message is outbound.
     */
    public function outbound(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => Message::DIRECTION_OUTBOUND,
        ]);
    }

    /**
     * Indicate that the message is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Message::STATUS_PENDING,
            'whatsapp_message_id' => null,
        ]);
    }
}
