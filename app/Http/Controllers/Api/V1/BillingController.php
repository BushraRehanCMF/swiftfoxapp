<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;

class BillingController
{
    /**
     * Get billing and account information.
     *
     * @return JsonResponse
     */
    public function getInfo(): JsonResponse
    {
        $user = auth()->user();
        $account = $user->account;

        return response()->json([
            'data' => [
                'id' => $account->id,
                'name' => $account->name,
                'timezone' => $account->timezone,
                'subscription_status' => $account->subscription_status,
                'trial' => [
                    'is_on_trial' => $account->isOnTrial(),
                    'is_expired' => $account->isTrialExpired(),
                    'ends_at' => $account->trial_ends_at,
                    'days_remaining' => $account->getRemainingTrialDays(),
                ],
                'subscription' => [
                    'has_active_subscription' => $account->hasActiveSubscription(),
                    'ends_at' => $account->subscription_ends_at,
                    'stripe_subscription_id' => $account->stripe_subscription_id,
                ],
                'usage' => [
                    'conversations_used' => $account->conversations_used,
                    'conversations_limit' => $account->conversations_limit,
                    'conversations_remaining' => $account->getRemainingConversations(),
                ],
                'whatsapp_connected' => $account->hasWhatsappConnected(),
                'can_send_messages' => $account->canSendMessages(),
            ],
        ], 200);
    }
}
