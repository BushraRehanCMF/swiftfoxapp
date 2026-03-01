<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'timezone' => $this->timezone,
            'subscription_status' => $this->subscription_status,
            'trial' => [
                'is_on_trial' => $this->isOnTrial(),
                'is_expired' => $this->isTrialExpired(),
                'ends_at' => $this->trial_ends_at,
                'days_remaining' => $this->getRemainingTrialDays(),
            ],
            'subscription' => [
                'has_active_subscription' => $this->hasActiveSubscription(),
                'ends_at' => $this->subscription_ends_at,
                'cancel_at_period_end' => (bool) $this->cancel_at_period_end,
                'stripe_subscription_id' => $this->stripe_subscription_id,
                'plan_name' => $this->getSubscriptionPlanName(),
            ],
            'usage' => [
                'conversations_used' => $this->conversations_used,
                'conversations_limit' => $this->conversations_limit,
                'conversations_remaining' => $this->getRemainingConversations(),
            ],
            'whatsapp_connected' => $this->hasWhatsappConnected(),
            'can_send_messages' => $this->canSendMessages(),
            'created_at' => $this->created_at,
        ];
    }
}
