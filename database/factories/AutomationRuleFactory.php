<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AutomationRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AutomationRule>
 */
class AutomationRuleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = AutomationRule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $triggerTypes = array_keys(AutomationRule::TRIGGER_TYPES);
        $actionTypes = array_keys(AutomationRule::ACTION_TYPES);

        return [
            'account_id' => Account::factory(),
            'name' => fake()->words(3, true) . ' Rule',
            'trigger_type' => fake()->randomElement($triggerTypes),
            'conditions' => null,
            'actions' => [
                [
                    'type' => fake()->randomElement($actionTypes),
                    'value' => fake()->randomNumber(3),
                ],
            ],
            'is_enabled' => true,
        ];
    }

    /**
     * Indicate that the rule is disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }

    /**
     * Set a specific trigger type.
     */
    public function withTrigger(string $triggerType): static
    {
        return $this->state(fn (array $attributes) => [
            'trigger_type' => $triggerType,
        ]);
    }

    /**
     * Set conditions for the rule.
     */
    public function withConditions(array $conditions): static
    {
        return $this->state(fn (array $attributes) => [
            'conditions' => $conditions,
        ]);
    }

    /**
     * Set actions for the rule.
     */
    public function withActions(array $actions): static
    {
        return $this->state(fn (array $attributes) => [
            'actions' => $actions,
        ]);
    }

    /**
     * Create a rule that assigns to a user.
     */
    public function assignUser(int $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'actions' => [
                [
                    'type' => AutomationRule::ACTION_ASSIGN_USER,
                    'value' => $userId,
                ],
            ],
        ]);
    }

    /**
     * Create a rule that adds a label.
     */
    public function addLabel(int $labelId): static
    {
        return $this->state(fn (array $attributes) => [
            'actions' => [
                [
                    'type' => AutomationRule::ACTION_ADD_LABEL,
                    'value' => $labelId,
                ],
            ],
        ]);
    }

    /**
     * Create a rule that sends an auto reply.
     */
    public function sendReply(string $message): static
    {
        return $this->state(fn (array $attributes) => [
            'actions' => [
                [
                    'type' => AutomationRule::ACTION_SEND_REPLY,
                    'value' => $message,
                ],
            ],
        ]);
    }
}
