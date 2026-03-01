<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class EmailVerificationService
{
    /**
     * Send verification email to user.
     */
    public function sendVerificationEmail(User $user): void
    {
        $user->notify(new VerifyEmail());
    }

    /**
     * Verify email from signed route.
     */
    public function verifyEmail(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return true;
        }

        $user->markEmailAsVerified();

        return true;
    }

    /**
     * Check if email is verified.
     */
    public function isVerified(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }
}
