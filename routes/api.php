<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\AutomationController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\BusinessHoursController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\LabelController;
use App\Http\Controllers\Api\V1\StripeWebhookController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\WhatsAppController;
use App\Http\Controllers\Api\V1\WhatsAppWebhookController;
use App\Http\Controllers\Api\V1\PricingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All API routes are prefixed with /api and use JSON responses.
| API versioning: /api/v1/...
|
*/

// API v1 Routes
Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/pricing', [PricingController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | Authentication Routes (Public) - Rate Limited
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,60'); // 5 per hour per IP
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,15'); // 10 per 15 min per IP
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,60'); // 5 per hour per IP
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,60'); // 5 per hour per IP
        Route::get('/verify-email/{user}', [AuthController::class, 'verifyEmail'])->name('auth.verify-email');
    });

    /*
    |--------------------------------------------------------------------------
    | Authenticated Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/user', [AuthController::class, 'user']);
        });

        /*
        |--------------------------------------------------------------
        | Billing
        |--------------------------------------------------------------
        */
        Route::get('/billing', [BillingController::class, 'getInfo']);

        /*
        |--------------------------------------------------------------------------
        | Account-Scoped Routes (requires active account/trial)
        |--------------------------------------------------------------------------
        */
        Route::middleware('account.active')->group(function () {

            /*
            |--------------------------------------------------------------
            | Inbox / Conversations
            |--------------------------------------------------------------
            */
            Route::prefix('conversations')->group(function () {
                Route::get('/', [ConversationController::class, 'index']);
                Route::get('/{conversation}', [ConversationController::class, 'show']);
                Route::get('/{conversation}/messages', [ConversationController::class, 'messages']);
                Route::post('/{conversation}/messages', [ConversationController::class, 'sendMessage'])
                    ->middleware('can.send');
                Route::post('/{conversation}/assign', [ConversationController::class, 'assign']);
                Route::post('/{conversation}/labels', [ConversationController::class, 'syncLabels']);
                Route::post('/{conversation}/close', [ConversationController::class, 'close']);
                Route::post('/{conversation}/reopen', [ConversationController::class, 'reopen']);
            });

            /*
            |--------------------------------------------------------------
            | Contacts
            |--------------------------------------------------------------
            */
            Route::prefix('contacts')->group(function () {
                Route::get('/', [ContactController::class, 'index']);
                Route::get('/{contact}', [ContactController::class, 'show']);
                Route::put('/{contact}', [ContactController::class, 'update']);
                Route::post('/{contact}/labels', [ContactController::class, 'syncLabels']);
                Route::get('/{contact}/conversations', [ContactController::class, 'conversations']);
            });

            /*
            |--------------------------------------------------------------
            | Labels
            |--------------------------------------------------------------
            */
            Route::prefix('labels')->group(function () {
                Route::get('/', [LabelController::class, 'index']);
                Route::post('/', [LabelController::class, 'store']);
                Route::get('/{label}', [LabelController::class, 'show']);
                Route::put('/{label}', [LabelController::class, 'update']);
                Route::delete('/{label}', [LabelController::class, 'destroy']);
            });

        });

        /*
        |--------------------------------------------------------------------------
        | Owner-Only Routes
        |--------------------------------------------------------------------------
        */
        Route::middleware('owner')->group(function () {

            /*
            |--------------------------------------------------------------
            | WhatsApp Connection (Owner Only)
            |--------------------------------------------------------------
            */
            Route::prefix('whatsapp')->group(function () {
                Route::get('/status', [WhatsAppController::class, 'status']);
                Route::get('/config', [WhatsAppController::class, 'config']);
                Route::post('/connect', [WhatsAppController::class, 'connect']);
                Route::post('/disconnect', [WhatsAppController::class, 'disconnect']);
            });

            /*
            |--------------------------------------------------------------
            | Team Management (Owner Only)
            |--------------------------------------------------------------
            */
            Route::prefix('team')->group(function () {
                Route::get('/', [TeamController::class, 'index']);
                Route::post('/invite', [TeamController::class, 'invite']);
                Route::put('/{user}/role', [TeamController::class, 'updateRole']);
                Route::delete('/{user}', [TeamController::class, 'remove']);
                Route::post('/{user}/resend-invite', [TeamController::class, 'resendInvite']);
            });

            /*
            |--------------------------------------------------------------
            | Automations (Owner Only)
            |--------------------------------------------------------------
            */
            Route::prefix('automations')->group(function () {
                Route::get('/', [AutomationController::class, 'index']);
                Route::post('/', [AutomationController::class, 'store']);
                Route::get('/triggers', [AutomationController::class, 'triggers']);
                Route::get('/actions', [AutomationController::class, 'actions']);
                Route::get('/{automation}', [AutomationController::class, 'show']);
                Route::put('/{automation}', [AutomationController::class, 'update']);
                Route::delete('/{automation}', [AutomationController::class, 'destroy']);
                Route::post('/{automation}/toggle', [AutomationController::class, 'toggle']);
            });

            /*
            |--------------------------------------------------------------
            | Business Hours (Owner Only)
            |--------------------------------------------------------------
            */
            Route::prefix('business-hours')->group(function () {
                Route::get('/', [BusinessHoursController::class, 'index']);
                Route::put('/', [BusinessHoursController::class, 'update']);
                Route::get('/check', [BusinessHoursController::class, 'check']);
            });

            /*
            |--------------------------------------------------------------
            | Billing & Subscription (Owner Only)
            |--------------------------------------------------------------
            */
            Route::prefix('checkout')->group(function () {
                Route::post('/session', [CheckoutController::class, 'createSession']);
                Route::get('/billing-portal', [CheckoutController::class, 'billingPortal']);
                Route::post('/cancel-subscription', [CheckoutController::class, 'cancelSubscription']);
            });

        });

    });

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Webhook Routes (Public - verified by signature)
    |--------------------------------------------------------------------------
    */
    Route::prefix('webhooks/whatsapp')->group(function () {
        Route::get('/', [WhatsAppWebhookController::class, 'verify']);
        Route::post('/', [WhatsAppWebhookController::class, 'handle']);
    });

    /*
    |--------------------------------------------------------------------------
    | Stripe Webhook Routes (Public - verified by signature)
    |--------------------------------------------------------------------------
    */
    Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);

    /*
    |--------------------------------------------------------------------------
    | Super Admin Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')->middleware(['auth:sanctum', 'super_admin'])->group(function () {
        // Admin routes will be added here
    });

});
