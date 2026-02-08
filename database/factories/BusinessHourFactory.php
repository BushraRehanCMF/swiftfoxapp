<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\BusinessHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BusinessHour>
 */
class BusinessHourFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = BusinessHour::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'day_of_week' => fake()->numberBetween(0, 6),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_enabled' => true,
        ];
    }

    /**
     * Set a specific day of week.
     */
    public function forDay(int $dayOfWeek): static
    {
        return $this->state(fn (array $attributes) => [
            'day_of_week' => $dayOfWeek,
        ]);
    }

    /**
     * Indicate that the day is disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }

    /**
     * Set custom hours.
     */
    public function withHours(string $start, string $end): static
    {
        return $this->state(fn (array $attributes) => [
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    /**
     * Create a full week of business hours.
     */
    public static function createWeekFor(Account $account, bool $weekendsEnabled = false): void
    {
        $days = [
            0 => $weekendsEnabled, // Sunday
            1 => true,             // Monday
            2 => true,             // Tuesday
            3 => true,             // Wednesday
            4 => true,             // Thursday
            5 => true,             // Friday
            6 => $weekendsEnabled, // Saturday
        ];

        foreach ($days as $day => $enabled) {
            BusinessHour::create([
                'account_id' => $account->id,
                'day_of_week' => $day,
                'start_time' => '09:00',
                'end_time' => '17:00',
                'is_enabled' => $enabled,
            ]);
        }
    }
}
