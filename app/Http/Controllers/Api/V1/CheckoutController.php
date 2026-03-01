<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Account;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Create a checkout session for subscription upgrade.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createSession(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Super admins cannot subscribe
        if ($user->isSuperAdmin()) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_ACTION',
                    'message' => 'Super administrators cannot create subscriptions.',
                ],
            ], 403);
        }

        $account = $user->account;

        // Validate request
        $validated = $request->validate([
            'price_id' => 'required|string', // Stripe Price ID
            'success_url' => 'required|url',
            'cancel_url' => 'required|url',
        ]);

        try {
            // Create checkout session
            $checkoutUrl = $this->subscriptionService->createCheckoutSession(
                $account,
                $validated['price_id'],
                $validated['success_url'],
                $validated['cancel_url'],
                0 // No trial for paid plans
            );

            return response()->json([
                'data' => [
                    'checkout_url' => $checkoutUrl,
                ],
                'message' => 'Checkout session created successfully.',
            ], 200);
        } catch (\Exception $e) {
            logger()->error('Failed to create checkout session', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => [
                    'code' => 'CHECKOUT_CREATION_FAILED',
                    'message' => 'Failed to create checkout session. Please try again.',
                ],
            ], 500);
        }
    }

    /**
     * Get billing portal URL for managing subscription.
     *
     * @return JsonResponse
     */
    public function billingPortal(Request $request): JsonResponse
    {
        $user = auth()->user();
        $account = $user->account;

        if (!$account->hasActiveSubscription()) {
            return response()->json([
                'error' => [
                    'code' => 'NO_ACTIVE_SUBSCRIPTION',
                    'message' => 'This account does not have an active subscription.',
                ],
            ], 403);
        }

        $returnUrl = $request->get('return_url', config('app.url') . '/billing');

        try {
            $portalUrl = $this->subscriptionService->createBillingPortalSession($account, $returnUrl);

            if (!$portalUrl) {
                throw new \Exception('Failed to create billing portal session');
            }

            return response()->json([
                'data' => [
                    'portal_url' => $portalUrl,
                ],
                'message' => 'Billing portal URL generated successfully.',
            ], 200);
        } catch (\Exception $e) {
            logger()->error('Failed to create billing portal', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => [
                    'code' => 'PORTAL_CREATION_FAILED',
                    'message' => 'Failed to access billing portal. Please try again.',
                ],
            ], 500);
        }
    }

    /**
     * Cancel subscription.
     *
     * @return JsonResponse
     */
    public function cancelSubscription(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Only account owner can cancel
        if (!$user->isOwner()) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Only account owners can cancel subscriptions.',
                ],
            ], 403);
        }

        $account = $user->account;

        if (!$account->stripe_subscription_id) {
            return response()->json([
                'error' => [
                    'code' => 'NO_SUBSCRIPTION',
                    'message' => 'This account does not have an active Stripe subscription.',
                ],
            ], 400);
        }

        try {
            $result = $this->subscriptionService->cancelSubscription($account);
            $account->refresh();

            return response()->json([
                'data' => [
                    'subscription_status' => $account->subscription_status,
                    'cancel_at_period_end' => $result['cancel_at_period_end'] ?? false,
                    'subscription_ends_at' => $result['subscription_ends_at'] ?? $account->subscription_ends_at,
                ],
                'message' => 'Subscription cancellation scheduled for period end.',
            ], 200);
        } catch (\Exception $e) {
            logger()->error('Failed to cancel subscription', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => [
                    'code' => 'CANCELLATION_FAILED',
                    'message' => 'Failed to cancel subscription. Please try again.',
                ],
            ], 500);
        }
    }
}
