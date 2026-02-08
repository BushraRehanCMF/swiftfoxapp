<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WhatsappConnection;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    public function __construct(
        protected WhatsAppService $whatsAppService
    ) {}

    /**
     * Get the current WhatsApp connection status.
     */
    public function status(Request $request): JsonResponse
    {
        $account = $request->user()->account;
        $connection = $account->whatsappConnection;

        if (!$connection) {
            return response()->json([
                'data' => [
                    'connected' => false,
                    'phone_number' => null,
                    'status' => null,
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'connected' => $connection->isActive(),
                'phone_number' => $connection->phone_number,
                'phone_number_id' => $connection->phone_number_id,
                'waba_id' => $connection->waba_id,
                'status' => $connection->status,
                'connected_at' => $connection->created_at,
            ],
        ]);
    }

    /**
     * Store WhatsApp connection from Meta Embedded Signup.
     * Called after the frontend completes the Meta Embedded Signup flow.
     */
    public function connect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'waba_id' => ['required', 'string'],
            'phone_number_id' => ['required', 'string'],
            'phone_number' => ['required', 'string'],
        ]);

        $account = $request->user()->account;

        // Check if already connected
        if ($account->whatsappConnection) {
            return response()->json([
                'error' => [
                    'code' => 'ALREADY_CONNECTED',
                    'message' => 'A WhatsApp number is already connected. Please disconnect first.',
                ],
            ], 409);
        }

        // Create the connection
        $connection = WhatsappConnection::create([
            'account_id' => $account->id,
            'waba_id' => $validated['waba_id'],
            'phone_number_id' => $validated['phone_number_id'],
            'phone_number' => $validated['phone_number'],
            'status' => WhatsappConnection::STATUS_ACTIVE,
        ]);

        return response()->json([
            'data' => [
                'connected' => true,
                'phone_number' => $connection->phone_number,
                'phone_number_id' => $connection->phone_number_id,
                'waba_id' => $connection->waba_id,
                'status' => $connection->status,
                'connected_at' => $connection->created_at,
            ],
            'message' => 'WhatsApp connected successfully.',
        ], 201);
    }

    /**
     * Disconnect WhatsApp.
     */
    public function disconnect(Request $request): JsonResponse
    {
        $account = $request->user()->account;
        $connection = $account->whatsappConnection;

        if (!$connection) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_CONNECTED',
                    'message' => 'No WhatsApp number is connected.',
                ],
            ], 404);
        }

        // Soft disconnect - just mark as disconnected
        $connection->update(['status' => WhatsappConnection::STATUS_DISCONNECTED]);

        return response()->json([
            'message' => 'WhatsApp disconnected successfully.',
        ]);
    }

    /**
     * Get Embedded Signup configuration for frontend.
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'data' => [
                'app_id' => config('swiftfox.whatsapp.app_id'),
                'config_id' => config('swiftfox.whatsapp.config_id'),
            ],
        ]);
    }
}
