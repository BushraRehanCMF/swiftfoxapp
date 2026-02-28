<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeWebhookController
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Handle Stripe webhook events.
     */
    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('swiftfox.stripe.webhook_secret');

        try {
            // Verify webhook signature
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $webhookSecret
            );
        } catch (\UnexpectedValueException $e) {
            logger()->warning('Invalid Stripe webhook payload', ['error' => $e->getMessage()]);
            return response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            logger()->warning('Stripe webhook signature verification failed', ['error' => $e->getMessage()]);
            return response('Invalid signature', 403);
        }

        try {
            // Handle the event based on type
            match ($event->type) {
                'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event->data->object),
                'customer.subscription.created' => $this->handleSubscriptionCreated($event->data->object),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
                'invoice.paid' => $this->handleInvoicePaid($event->data->object),
                'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event->data->object),
                default => null,
            };

            return response('Webhook handled', 200);
        } catch (\Exception $e) {
            logger()->error('Error handling Stripe webhook', [
                'event_type' => $event->type,
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            // Return success anyway to prevent retries
            return response('Error processed', 200);
        }
    }

    /**
     * Handle checkout.session.completed event.
     */
    protected function handleCheckoutSessionCompleted(object $session): void
    {
        // The subscription is automatically created by Stripe when checkout completes
        // We handle the subscription creation event instead
        logger()->info('Checkout session completed', [
            'session_id' => $session->id,
            'customer_id' => $session->customer,
        ]);
    }

    /**
     * Handle customer.subscription.created event.
     */
    protected function handleSubscriptionCreated(object $subscription): void
    {
        $this->subscriptionService->handleSubscriptionCreated((array) $subscription);
    }

    /**
     * Handle customer.subscription.updated event.
     */
    protected function handleSubscriptionUpdated(object $subscription): void
    {
        logger()->info('Subscription updated', [
            'subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer,
            'status' => $subscription->status,
        ]);
    }

    /**
     * Handle customer.subscription.deleted event.
     */
    protected function handleSubscriptionDeleted(object $subscription): void
    {
        logger()->info('Subscription deleted', [
            'subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer,
        ]);
    }

    /**
     * Handle invoice.paid event.
     */
    protected function handleInvoicePaid(object $invoice): void
    {
        $this->subscriptionService->handleInvoicePaid((array) $invoice);
    }

    /**
     * Handle invoice.payment_failed event.
     */
    protected function handleInvoicePaymentFailed(object $invoice): void
    {
        $this->subscriptionService->handleInvoicePaymentFailed((array) $invoice);
    }
}
