<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * Handle an incoming request.
     * Ensures the user's account has an active subscription or valid trial.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Super admins bypass all checks
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // User must belong to an account
        if (!$user->hasAccount()) {
            return response()->json([
                'error' => [
                    'code' => 'NO_ACCOUNT',
                    'message' => 'Your user account is not associated with any organization.',
                ],
            ], 403);
        }

        $account = $user->account;

        // Check if account has active subscription or valid trial
        if (!$account->hasActiveSubscription() && !$account->isOnTrial()) {
            return response()->json([
                'error' => [
                    'code' => 'SUBSCRIPTION_REQUIRED',
                    'message' => 'Your trial has expired. Please upgrade to continue.',
                    'details' => [
                        'trial_ended_at' => $account->trial_ends_at,
                        'subscription_status' => $account->subscription_status,
                    ],
                ],
            ], 402); // Payment Required
        }

        return $next($request);
    }
}
