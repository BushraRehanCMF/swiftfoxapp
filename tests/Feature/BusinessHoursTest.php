<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BusinessHour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessHoursTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $member;
    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create([
            'timezone' => 'America/New_York',
        ]);
        $this->owner = User::factory()->create([
            'account_id' => $this->account->id,
            'role' => 'owner',
        ]);
        $this->member = User::factory()->create([
            'account_id' => $this->account->id,
            'role' => 'member',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization Tests
    |--------------------------------------------------------------------------
    */

    public function test_owner_can_access_business_hours(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/business-hours');

        $response->assertOk();
    }

    public function test_member_cannot_access_business_hours(): void
    {
        $response = $this->actingAs($this->member)
            ->getJson('/api/v1/business-hours');

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_business_hours(): void
    {
        $response = $this->getJson('/api/v1/business-hours');

        $response->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | Get Business Hours Tests
    |--------------------------------------------------------------------------
    */

    public function test_owner_can_get_business_hours(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/business-hours');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'timezone',
                    'hours',
                ],
            ]);
    }

    public function test_returns_default_hours_when_none_set(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/business-hours');

        $response->assertOk()
            ->assertJsonCount(7, 'data.hours');

        $hours = $response->json('data.hours');

        // Check Monday-Friday are enabled (days 1-5)
        foreach ([1, 2, 3, 4, 5] as $day) {
            $dayHour = collect($hours)->firstWhere('day_of_week', $day);
            $this->assertTrue($dayHour['is_enabled'], "Day {$day} should be enabled");
        }

        // Check Saturday-Sunday are disabled (days 0, 6)
        foreach ([0, 6] as $day) {
            $dayHour = collect($hours)->firstWhere('day_of_week', $day);
            $this->assertFalse($dayHour['is_enabled'], "Day {$day} should be disabled");
        }
    }

    public function test_returns_saved_hours(): void
    {
        // Create custom business hours
        BusinessHour::create([
            'account_id' => $this->account->id,
            'day_of_week' => 1,
            'start_time' => '10:00',
            'end_time' => '18:00',
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/business-hours');

        $response->assertOk();

        $hours = $response->json('data.hours');
        $mondayHour = collect($hours)->firstWhere('day_of_week', 1);

        $this->assertStringStartsWith('10:00', $mondayHour['start_time']);
        $this->assertStringStartsWith('18:00', $mondayHour['end_time']);
    }

    public function test_includes_timezone(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/business-hours');

        $response->assertOk()
            ->assertJsonPath('data.timezone', 'America/New_York');
    }

    public function test_includes_day_names(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/business-hours');

        $response->assertOk();

        $hours = $response->json('data.hours');
        $dayNames = collect($hours)->pluck('day_name')->toArray();

        $this->assertContains('Sunday', $dayNames);
        $this->assertContains('Monday', $dayNames);
        $this->assertContains('Saturday', $dayNames);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Business Hours Tests
    |--------------------------------------------------------------------------
    */

    public function test_owner_can_update_business_hours(): void
    {
        $hours = [
            ['day_of_week' => 0, 'start_time' => '10:00', 'end_time' => '14:00', 'is_enabled' => true],
            ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 4, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 5, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 6, 'start_time' => '10:00', 'end_time' => '14:00', 'is_enabled' => false],
        ];

        $response = $this->actingAs($this->owner)
            ->putJson('/api/v1/business-hours', [
                'hours' => $hours,
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Business hours updated successfully.');

        $this->assertDatabaseCount('business_hours', 7);
        $this->assertDatabaseHas('business_hours', [
            'account_id' => $this->account->id,
            'day_of_week' => 0,
            'is_enabled' => true,
        ]);
    }

    public function test_owner_can_update_timezone(): void
    {
        $hours = [
            ['day_of_week' => 0, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => false],
            ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 4, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 5, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 6, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => false],
        ];

        $response = $this->actingAs($this->owner)
            ->putJson('/api/v1/business-hours', [
                'timezone' => 'Europe/London',
                'hours' => $hours,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.timezone', 'Europe/London');

        $this->assertDatabaseHas('accounts', [
            'id' => $this->account->id,
            'timezone' => 'Europe/London',
        ]);
    }

    public function test_update_requires_all_seven_days(): void
    {
        $response = $this->actingAs($this->owner)
            ->putJson('/api/v1/business-hours', [
                'hours' => [
                    ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['hours']);
    }

    public function test_update_requires_valid_time_format(): void
    {
        $hours = [
            ['day_of_week' => 0, 'start_time' => 'invalid', 'end_time' => '17:00', 'is_enabled' => false],
            ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 4, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 5, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 6, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => false],
        ];

        $response = $this->actingAs($this->owner)
            ->putJson('/api/v1/business-hours', [
                'hours' => $hours,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['hours.0.start_time']);
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $hours = [
            ['day_of_week' => 0, 'start_time' => '17:00', 'end_time' => '09:00', 'is_enabled' => false],
            ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 4, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 5, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 6, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => false],
        ];

        $response = $this->actingAs($this->owner)
            ->putJson('/api/v1/business-hours', [
                'hours' => $hours,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['hours.0.end_time']);
    }

    public function test_update_requires_valid_timezone(): void
    {
        $hours = [
            ['day_of_week' => 0, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => false],
            ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 4, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 5, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 6, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => false],
        ];

        $response = $this->actingAs($this->owner)
            ->putJson('/api/v1/business-hours', [
                'timezone' => 'Invalid/Timezone',
                'hours' => $hours,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['timezone']);
    }

    public function test_member_cannot_update_business_hours(): void
    {
        $hours = [
            ['day_of_week' => 0, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => false],
            ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 4, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 5, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],
            ['day_of_week' => 6, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => false],
        ];

        $response = $this->actingAs($this->member)
            ->putJson('/api/v1/business-hours', [
                'hours' => $hours,
            ]);

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Check Business Hours Tests
    |--------------------------------------------------------------------------
    */

    public function test_owner_can_check_business_hours(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/business-hours/check');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'is_open',
                    'timezone',
                    'current_time',
                    'current_day',
                ],
            ]);
    }

    public function test_check_returns_open_during_business_hours(): void
    {
        // Set business hours for today
        $now = now($this->account->timezone);
        $dayOfWeek = $now->dayOfWeek;

        BusinessHour::create([
            'account_id' => $this->account->id,
            'day_of_week' => $dayOfWeek,
            'start_time' => '00:00',
            'end_time' => '23:59',
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/business-hours/check');

        $response->assertOk()
            ->assertJsonPath('data.is_open', true);
    }

    public function test_check_returns_closed_when_no_hours_set(): void
    {
        // Delete any existing business hours
        BusinessHour::where('account_id', $this->account->id)->delete();

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/business-hours/check');

        $response->assertOk()
            ->assertJsonPath('data.is_open', false);
    }

    public function test_check_returns_closed_when_day_disabled(): void
    {
        $now = now($this->account->timezone);
        $dayOfWeek = $now->dayOfWeek;

        BusinessHour::create([
            'account_id' => $this->account->id,
            'day_of_week' => $dayOfWeek,
            'start_time' => '00:00',
            'end_time' => '23:59',
            'is_enabled' => false,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/business-hours/check');

        $response->assertOk()
            ->assertJsonPath('data.is_open', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Multi-tenancy Tests
    |--------------------------------------------------------------------------
    */

    public function test_business_hours_are_scoped_to_account(): void
    {
        $otherAccount = Account::factory()->create();

        // Create hours for other account
        BusinessHour::create([
            'account_id' => $otherAccount->id,
            'day_of_week' => 0,
            'start_time' => '08:00',
            'end_time' => '20:00',
            'is_enabled' => true,
        ]);

        // Create hours for this account
        BusinessHour::create([
            'account_id' => $this->account->id,
            'day_of_week' => 0,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/business-hours');

        $response->assertOk();

        $hours = $response->json('data.hours');
        $sundayHour = collect($hours)->firstWhere('day_of_week', 0);

        // Should see this account's hours, not the other account's
        $this->assertStringStartsWith('09:00', $sundayHour['start_time']);
    }
}
