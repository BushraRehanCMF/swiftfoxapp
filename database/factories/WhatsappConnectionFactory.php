<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\WhatsappConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WhatsappConnection>
 */
class WhatsappConnectionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = WhatsappConnection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'waba_id' => fake()->numerify('###############'),
            'phone_number_id' => fake()->numerify('###############'),
            'phone_number' => fake()->e164PhoneNumber(),
            'access_token' => 'fake_access_token_' . fake()->sha256(),
            'status' => WhatsappConnection::STATUS_ACTIVE,
        ];
    }

    /**
     * Indicate that the connection is disconnected.
     */
    public function disconnected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WhatsappConnection::STATUS_DISCONNECTED,
        ]);
    }
}
