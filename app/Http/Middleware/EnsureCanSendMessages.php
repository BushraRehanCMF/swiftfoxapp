<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanSendMessages
{
    /**
     * Handle an incoming request.
     * Ensures the account can send WhatsApp messages (has credits and is active).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Super admins cannot send messages
        if ($user->isSuperAdmin()) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Super admins cannot send WhatsApp messages.',
                ],
            ], 403);
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

        // Check if account can send messages
        if (!$account->canSendMessages()) {
            // Determine the reason
            if (!$account->hasActiveSubscription() && !$account->isOnTrial()) {
                return response()->json([
                    'error' => [
                        'code' => 'SUBSCRIPTION_REQUIRED',
                        'message' => 'Your trial has expired. Please upgrade to send messages.',
                    ],
                ], 402);
            }

            if ($account->conversations_used >= $account->conversations_limit) {
                return response()->json([
                    'error' => [
                        'code' => 'CONVERSATION_LIMIT_REACHED',
                        'message' => 'You have reached your conversation limit. Please upgrade to continue.',
                        'details' => [
                            'conversations_used' => $account->conversations_used,
                            'conversations_limit' => $account->conversations_limit,
                        ],
                    ],
                ], 402);
            }
        }

        // Check if WhatsApp is connected
        if (!$account->hasWhatsappConnected()) {
            return response()->json([
                'error' => [
                    'code' => 'WHATSAPP_NOT_CONNECTED',
                    'message' => 'Please connect your WhatsApp Business number first.',
                ],
            ], 400);
        }

        return $next($request);
    }
}
