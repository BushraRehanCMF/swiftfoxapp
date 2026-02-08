<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BusinessHour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessHoursController extends Controller
{
    /**
     * Get business hours for the account.
     */
    public function index(Request $request): JsonResponse
    {
        $account = $request->user()->account;

        $businessHours = BusinessHour::where('account_id', $account->id)
            ->orderBy('day_of_week')
            ->get();

        // If no business hours exist, return defaults
        if ($businessHours->isEmpty()) {
            $businessHours = $this->getDefaultBusinessHours();
        }

        return response()->json([
            'data' => [
                'timezone' => $account->timezone,
                'hours' => $businessHours->map(fn ($hour) => [
                    'id' => $hour->id ?? null,
                    'day_of_week' => $hour->day_of_week,
                    'day_name' => $this->getDayName($hour->day_of_week),
                    'start_time' => $hour->start_time,
                    'end_time' => $hour->end_time,
                    'is_enabled' => $hour->is_enabled,
                ]),
            ],
        ]);
    }

    /**
     * Update business hours for the account.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'timezone' => ['sometimes', 'string', 'timezone'],
            'hours' => ['required', 'array', 'size:7'],
            'hours.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'hours.*.start_time' => ['required', 'date_format:H:i'],
            'hours.*.end_time' => ['required', 'date_format:H:i', 'after:hours.*.start_time'],
            'hours.*.is_enabled' => ['required', 'boolean'],
        ]);

        $account = $request->user()->account;

        // Update timezone if provided
        if (isset($validated['timezone'])) {
            $account->update(['timezone' => $validated['timezone']]);
        }

        // Update or create business hours
        foreach ($validated['hours'] as $hourData) {
            BusinessHour::updateOrCreate(
                [
                    'account_id' => $account->id,
                    'day_of_week' => $hourData['day_of_week'],
                ],
                [
                    'start_time' => $hourData['start_time'],
                    'end_time' => $hourData['end_time'],
                    'is_enabled' => $hourData['is_enabled'],
                ]
            );
        }

        // Fetch updated hours
        $businessHours = BusinessHour::where('account_id', $account->id)
            ->orderBy('day_of_week')
            ->get();

        return response()->json([
            'data' => [
                'timezone' => $account->fresh()->timezone,
                'hours' => $businessHours->map(fn ($hour) => [
                    'id' => $hour->id,
                    'day_of_week' => $hour->day_of_week,
                    'day_name' => $this->getDayName($hour->day_of_week),
                    'start_time' => $hour->start_time,
                    'end_time' => $hour->end_time,
                    'is_enabled' => $hour->is_enabled,
                ]),
            ],
            'message' => 'Business hours updated successfully.',
        ]);
    }

    /**
     * Check if currently within business hours.
     */
    public function check(Request $request): JsonResponse
    {
        $account = $request->user()->account;
        $isOpen = $this->isWithinBusinessHours($account);

        return response()->json([
            'data' => [
                'is_open' => $isOpen,
                'timezone' => $account->timezone,
                'current_time' => now($account->timezone)->format('H:i'),
                'current_day' => now($account->timezone)->dayOfWeek,
            ],
        ]);
    }

    /**
     * Check if the account is within business hours.
     */
    protected function isWithinBusinessHours($account): bool
    {
        $now = now($account->timezone);
        $dayOfWeek = $now->dayOfWeek;
        $currentTime = $now->format('H:i');

        $businessHour = BusinessHour::where('account_id', $account->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_enabled', true)
            ->first();

        if (!$businessHour) {
            return false;
        }

        return $currentTime >= $businessHour->start_time
            && $currentTime <= $businessHour->end_time;
    }

    /**
     * Get default business hours (Mon-Fri 9-17, Sat-Sun off).
     */
    protected function getDefaultBusinessHours(): \Illuminate\Support\Collection
    {
        return collect([
            ['day_of_week' => 0, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => false], // Sunday
            ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],  // Monday
            ['day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],  // Tuesday
            ['day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],  // Wednesday
            ['day_of_week' => 4, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],  // Thursday
            ['day_of_week' => 5, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => true],  // Friday
            ['day_of_week' => 6, 'start_time' => '09:00', 'end_time' => '17:00', 'is_enabled' => false], // Saturday
        ])->map(fn ($data) => (object) $data);
    }

    /**
     * Get day name from day number.
     */
    protected function getDayName(int $dayOfWeek): string
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        return $days[$dayOfWeek] ?? '';
    }
}
