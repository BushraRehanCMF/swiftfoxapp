<?php

namespace App\Services;

use App\Models\Account;
use Stripe\StripeClient;

class SubscriptionService
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('swiftfox.stripe.secret'));
    }

    /**
     * Create a Stripe customer for the account if it doesn't exist.
     */
    public function getOrCreateStripeCustomer(Account $account, string $email, string $name): string
    {
        // Reuse existing customer if it exists in the currently configured Stripe account
        if ($account->stripe_customer_id) {
            try {
                $this->stripe->customers->retrieve($account->stripe_customer_id, []);

                return $account->stripe_customer_id;
            } catch (\Exception $exception) {
                logger()->warning('Stored Stripe customer is invalid for current account, recreating customer', [
                    'account_id' => $account->id,
                    'stripe_customer_id' => $account->stripe_customer_id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        // Create new Stripe customer
        $customer = $this->stripe->customers->create([
            'email' => $email,
            'name' => $name,
            'metadata' => [
                'account_id' => $account->id,
            ],
        ]);

        // Store the Stripe customer ID
        $account->update(['stripe_customer_id' => $customer->id]);

        return $customer->id;
    }

    /**
     * Create a checkout session for subscription upgrade.
     */
    public function createCheckoutSession(
        Account $account,
        string $priceId,
        string $successUrl,
        string $cancelUrl,
        ?int $trialDays = null
    ): string {
        $customerId = $this->getOrCreateStripeCustomer(
            $account,
            $account->owner->email,
            $account->name
        );

        $sessionData = [
            'customer' => $customerId,
            'mode' => 'subscription',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items' => [
                [
                    'price' => $priceId,
                    'quantity' => 1,
                ],
            ],
        ];

        // Add trial period if provided
        if ($trialDays) {
            $sessionData['subscription_data']['trial_period_days'] = $trialDays;
        }

        $session = $this->stripe->checkout->sessions->create($sessionData);

        return $session->url;
    }

    /**
     * Handle subscription created event from webhook.
     */
    public function handleSubscriptionCreated(object $subscription): void
    {
        logger()->info('handleSubscriptionCreated called', ['subscription_id' => $subscription->id]);

        $customerId = $subscription->customer;
        $subscriptionId = $subscription->id;
        $priceId = $subscription->items->data[0]->price->id ?? null;
        $currentPeriodEnd = $subscription->current_period_end;

        logger()->info('Extracted subscription data', [
            'customer_id' => $customerId,
            'subscription_id' => $subscriptionId,
            'price_id' => $priceId,
            'current_period_end' => $currentPeriodEnd,
        ]);

        // Find account by Stripe customer ID
        $account = Account::where('stripe_customer_id', $customerId)->first();

        if (!$account) {
            logger()->info('Account not found by customer_id, trying metadata');
            // Try to find by customer metadata
            try {
                $customer = $this->stripe->customers->retrieve($customerId);
                $accountId = $customer->metadata['account_id'] ?? null;
                $account = $accountId ? Account::find($accountId) : null;
                logger()->info('Retrieved customer from Stripe', ['account_id' => $accountId]);
            } catch (\Exception $e) {
                logger()->error('Failed to retrieve Stripe customer', ['customer_id' => $customerId, 'error' => $e->getMessage()]);
                return;
            }
        }

        if (!$account) {
            logger()->warning('Account not found for Stripe subscription', ['customer_id' => $customerId]);
            return;
        }

        logger()->info('Found account, updating subscription', ['account_id' => $account->id]);

        // Determine subscription end date
        $subscriptionEndsAt = $currentPeriodEnd ? now()->setTimestamp($currentPeriodEnd) : now()->addMonth();

        // Update account with subscription
        $account->update([
            'stripe_subscription_id' => $subscriptionId,
            'stripe_product_id' => $priceId,
            'subscription_status' => Account::STATUS_ACTIVE,
            'subscription_ends_at' => $subscriptionEndsAt,
            'conversations_used' => 0, // Reset on new subscription
            'conversations_limit' => 500, // Default paid plan limit (customize as needed)
        ]);

        logger()->info('Subscription created', ['account_id' => $account->id, 'subscription_id' => $subscriptionId]);
    }

    /**
     * Handle subscription updated event from webhook.
     */
    public function handleSubscriptionUpdated(object $subscription): void
    {
        $subscriptionId = $subscription->id ?? null;
        $customerId = $subscription->customer ?? null;
        $status = $subscription->status ?? null;
        $cancelAtPeriodEnd = (bool) ($subscription->cancel_at_period_end ?? false);
        $currentPeriodEnd = $subscription->current_period_end ?? null;

        if (!$subscriptionId) {
            logger()->warning('Subscription updated webhook missing subscription ID');
            return;
        }

        $account = Account::where('stripe_subscription_id', $subscriptionId)->first();

        if (!$account && $customerId) {
            $account = Account::where('stripe_customer_id', $customerId)->first();
        }

        if (!$account) {
            logger()->warning('Account not found for subscription update', [
                'subscription_id' => $subscriptionId,
                'customer_id' => $customerId,
            ]);
            return;
        }

        $updates = [
            'subscription_status' => in_array($status, ['canceled', 'incomplete_expired'], true)
                ? Account::STATUS_CANCELLED
                : Account::STATUS_ACTIVE,
        ];

        if ($currentPeriodEnd) {
            $updates['subscription_ends_at'] = now()->setTimestamp($currentPeriodEnd);
        }

        if (in_array($status, ['canceled', 'incomplete_expired'], true)) {
            $updates['stripe_subscription_id'] = null;
        }

        $account->update($updates);

        logger()->info('Subscription updated webhook synced', [
            'account_id' => $account->id,
            'subscription_id' => $subscriptionId,
            'status' => $status,
            'cancel_at_period_end' => $cancelAtPeriodEnd,
        ]);
    }

    /**
     * Handle subscription deleted event from webhook.
     */
    public function handleSubscriptionDeleted(object $subscription): void
    {
        $subscriptionId = $subscription->id ?? null;
        $customerId = $subscription->customer ?? null;

        if (!$subscriptionId) {
            logger()->warning('Subscription deleted webhook missing subscription ID');
            return;
        }

        $account = Account::where('stripe_subscription_id', $subscriptionId)->first();

        if (!$account && $customerId) {
            $account = Account::where('stripe_customer_id', $customerId)->first();
        }

        if (!$account) {
            logger()->warning('Account not found for subscription deletion', [
                'subscription_id' => $subscriptionId,
                'customer_id' => $customerId,
            ]);
            return;
        }

        $account->update([
            'subscription_status' => Account::STATUS_CANCELLED,
            'stripe_subscription_id' => null,
        ]);

        logger()->info('Subscription deleted webhook synced', [
            'account_id' => $account->id,
            'subscription_id' => $subscriptionId,
        ]);
    }

    /**
     * Handle invoice paid event from webhook.
     */
    public function handleInvoicePaid(array $invoiceData): void
    {
        $subscriptionId = $invoiceData['subscription'] ?? null;
        $periodEnd = $invoiceData['lines']['data'][0]['period']['end'] ?? null;

        if (!$subscriptionId) {
            logger()->warning('Invoice paid webhook received without subscription ID');
            return;
        }

        // Find account by Stripe subscription ID
        $account = Account::where('stripe_subscription_id', $subscriptionId)->first();

        if (!$account) {
            logger()->warning('Account not found for Stripe subscription', ['subscription_id' => $subscriptionId]);
            return;
        }

        // Update subscription period end
        if ($periodEnd) {
            $account->update([
                'subscription_ends_at' => now()->setTimestamp($periodEnd),
                'conversations_used' => 0, // Reset conversations for new billing period
            ]);
        }

        logger()->info('Invoice paid', ['account_id' => $account->id, 'subscription_id' => $subscriptionId]);
    }

    /**
     * Handle invoice payment failed event from webhook.
     */
    public function handleInvoicePaymentFailed(array $invoiceData): void
    {
        $subscriptionId = $invoiceData['subscription'] ?? null;

        if (!$subscriptionId) {
            logger()->warning('Invoice payment failed webhook received without subscription ID');
            return;
        }

        $account = Account::where('stripe_subscription_id', $subscriptionId)->first();

        if (!$account) {
            logger()->warning('Account not found for failed payment', ['subscription_id' => $subscriptionId]);
            return;
        }

        logger()->warning('Payment failed', ['account_id' => $account->id, 'subscription_id' => $subscriptionId]);

        // Optionally notify the customer to update payment method
        // NotificationService::notifyPaymentFailed($account);
    }

    /**
     * Cancel a subscription.
     */
    public function cancelSubscription(Account $account): array
    {
        if (!$account->stripe_subscription_id) {
            throw new \RuntimeException('No active Stripe subscription found.');
        }

        try {
            $subscription = $this->stripe->subscriptions->update($account->stripe_subscription_id, [
                'cancel_at_period_end' => true,
            ]);

            $currentPeriodEnd = $subscription->current_period_end ?? null;
            $subscriptionEndsAt = $currentPeriodEnd
                ? now()->setTimestamp($currentPeriodEnd)
                : $account->subscription_ends_at;

            $account->update([
                'subscription_status' => Account::STATUS_ACTIVE,
                'subscription_ends_at' => $subscriptionEndsAt,
            ]);

            logger()->info('Subscription cancellation scheduled at period end', [
                'account_id' => $account->id,
                'subscription_id' => $account->stripe_subscription_id,
                'current_period_end' => $currentPeriodEnd,
            ]);

            return [
                'cancel_at_period_end' => (bool) ($subscription->cancel_at_period_end ?? true),
                'subscription_ends_at' => $subscriptionEndsAt,
            ];
        } catch (\Exception $e) {
            logger()->error('Failed to cancel subscription', [
                'account_id' => $account->id,
                'subscription_id' => $account->stripe_subscription_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get Stripe subscription details.
     */
    public function getSubscription(Account $account): ?object
    {
        if (!$account->stripe_subscription_id) {
            return null;
        }

        try {
            return $this->stripe->subscriptions->retrieve($account->stripe_subscription_id);
        } catch (\Exception $e) {
            logger()->error('Failed to retrieve subscription', [
                'account_id' => $account->id,
                'subscription_id' => $account->stripe_subscription_id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Create a Stripe billing portal session for the customer.
     */
    public function createBillingPortalSession(Account $account, string $returnUrl): ?string
    {
        if (!$account->stripe_customer_id) {
            return null;
        }

        try {
            $session = $this->stripe->billingPortal->sessions->create([
                'customer' => $account->stripe_customer_id,
                'return_url' => $returnUrl,
            ]);

            return $session->url;
        } catch (\Exception $e) {
            logger()->error('Failed to create billing portal session', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
