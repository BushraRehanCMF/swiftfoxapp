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
     * Exchanges authorization code for WABA ID and phone number ID.
     */
    public function connect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
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

        try {
            // Exchange code for access token and WABA information
            $wabaData = $this->whatsAppService->exchangeCodeForWabaInfo($validated['code']);

            // Create the connection
            $connection = WhatsappConnection::create([
                'account_id' => $account->id,
                'waba_id' => $wabaData['waba_id'],
                'phone_number_id' => $wabaData['phone_number_id'],
                'phone_number' => $wabaData['phone_number'],
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
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code' => 'EXCHANGE_FAILED',
                    'message' => 'Unable to exchange code for WhatsApp connection: ' . $e->getMessage(),
                ],
            ], 400);
        }
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
