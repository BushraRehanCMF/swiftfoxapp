<?php

namespace App\Services;

use App\Models\Account;
use App\Models\User;
use App\Notifications\TeamInvitation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class TeamService
{
    /**
     * Invite a new user to the account.
     */
    public function inviteUser(Account $account, string $name, string $email, string $role = 'member'): User
    {
        // Create user with temporary password
        $temporaryPassword = Str::random(16);

        $user = User::create([
            'account_id' => $account->id,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($temporaryPassword),
            'role' => $role,
        ]);

        // Send invitation email with password reset link
        $this->sendInvitationEmail($user);

        return $user;
    }

    /**
     * Remove a user from the account.
     */
    public function removeUser(User $user): void
    {
        // Unassign from any conversations
        $user->assignedConversations()->update(['assigned_user_id' => null]);

        // Delete the user
        $user->delete();
    }

    /**
     * Resend invitation email to a user.
     */
    public function resendInvitation(User $user): void
    {
        $this->sendInvitationEmail($user);
    }

    /**
     * Send invitation email with password reset link.
     */
    protected function sendInvitationEmail(User $user): void
    {
        // Generate password reset token
        $token = Password::createToken($user);

        // Send the invitation notification
        $user->notify(new TeamInvitation($token));
    }
}
