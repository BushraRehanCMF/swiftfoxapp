<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    protected $fillable = ['email', 'ip_address', 'failed_attempts', 'locked_until'];

    protected $casts = [
        'locked_until' => 'datetime',
    ];

    /**
     * Check if the account is locked.
     */
    public static function isLocked(string $email, string $ipAddress): bool
    {
        $attempt = self::where('email', $email)
            ->where('ip_address', $ipAddress)
            ->first();

        if (!$attempt) {
            return false;
        }

        if ($attempt->locked_until && now()->lessThan($attempt->locked_until)) {
            return true;
        }

        return false;
    }

    /**
     * Record a failed login attempt.
     */
    public static function recordFailedAttempt(string $email, string $ipAddress): void
    {
        $attempt = self::where('email', $email)
            ->where('ip_address', $ipAddress)
            ->first();

        if (!$attempt) {
            $attempt = self::create([
                'email' => $email,
                'ip_address' => $ipAddress,
                'failed_attempts' => 1,
            ]);
        } else {
            $attempt->increment('failed_attempts');
        }

        // Lock after 5 failed attempts for 15 minutes
        if ($attempt->failed_attempts >= 5) {
            $attempt->update(['locked_until' => now()->addMinutes(15)]);
        }
    }

    /**
     * Clear failed attempts on successful login.
     */
    public static function clearAttempts(string $email, string $ipAddress): void
    {
        self::where('email', $email)
            ->where('ip_address', $ipAddress)
            ->update(['failed_attempts' => 0, 'locked_until' => null]);
    }
}
