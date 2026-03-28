<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Message;
use App\Models\WhatsappConnection;
use App\Services\ConversationService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    public function __construct(
        protected WhatsAppService $whatsAppService,
        protected ConversationService $conversationService
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
     * Exchanges authorization code or input_token for WABA info.
     */
    public function connect(Request $request): JsonResponse
    {
        \Log::info('🔗 WhatsApp connect endpoint called', [
            'user_id' => $request->user()->id,
            'account_id' => $request->user()->account->id,
            'has_code' => (bool) $request->input('code'),
            'is_input_token' => (bool) $request->input('is_input_token'),
        ]);

        $validated = $request->validate([
            'code' => ['sometimes', 'nullable', 'string'],
            'access_token' => ['sometimes', 'nullable', 'string'],
            'is_input_token' => ['sometimes', 'boolean'],
            'redirect_uri' => ['sometimes', 'nullable', 'string'],
            'waba_id' => ['sometimes', 'nullable', 'string'],
            'phone_number_id' => ['sometimes', 'nullable', 'string'],
        ]);

        if (empty($validated['code']) && empty($validated['access_token'])) {
            return response()->json([
                'error' => [
                    'code' => 'MISSING_AUTH',
                    'message' => 'Provide either code or access_token.',
                ],
            ], 422);
        }

        $account = $request->user()->account;
        $code = $validated['code'] ?? null;
        $accessToken = $validated['access_token'] ?? null;
        $isInputToken = $validated['is_input_token'] ?? false;
        $redirectUri = $validated['redirect_uri'] ?? null;
        $wabaId = $validated['waba_id'] ?? null;
        $phoneNumberId = $validated['phone_number_id'] ?? null;

        \Log::info('✅ Request validated', [
            'token_type' => $accessToken ? 'access_token' : ($isInputToken ? 'input_token (JWT)' : 'authorization_code'),
            'code_preview' => $code ? substr($code, 0, 30) . '...' : null,
            'access_token_preview' => $accessToken ? substr($accessToken, 0, 30) . '...' : null,
            'redirect_uri' => $redirectUri,
        ]);

        $existingConnection = $account->whatsappConnection;

        // Only block when an active connection already exists.
        if ($existingConnection && $existingConnection->isActive()) {
            \Log::warning('⚠️  Account already has WhatsApp connection', [
                'account_id' => $account->id,
                'existing_phone' => $existingConnection->phone_number,
            ]);
            return response()->json([
                'error' => [
                    'code' => 'ALREADY_CONNECTED',
                    'message' => 'A WhatsApp number is already connected. Please disconnect first.',
                ],
            ], 409);
        }

        try {
            \Log::info('🔄 Starting authorization exchange with WhatsAppService');

            // If waba_id and phone_number_id were provided from Embedded Signup session info,
            // use the direct approach (exchange code for token, then use session info directly)
            if ($wabaId && $phoneNumberId) {
                \Log::info('📩 Using Embedded Signup session info', [
                    'waba_id' => $wabaId,
                    'phone_number_id' => $phoneNumberId,
                ]);

                $wabaData = $accessToken
                    ? $this->whatsAppService->processEmbeddedSignup($accessToken, $wabaId, $phoneNumberId)
                    : $this->whatsAppService->processEmbeddedSignupWithCode($code, $wabaId, $phoneNumberId, $redirectUri);
            } else {
                // Fallback: try to discover WABA info from Graph API (may fail without business_management)
                $wabaData = $accessToken
                    ? $this->whatsAppService->exchangeAccessTokenForWabaInfo($accessToken)
                    : $this->whatsAppService->exchangeCodeForWabaInfo(
                        $code,
                        $isInputToken,
                        $redirectUri
                    );
            }

            \Log::info('✅ Authorization exchange successful, creating WhatsappConnection', [
                'waba_id' => $wabaData['waba_id'],
                'phone_number' => $wabaData['phone_number'],
            ]);

            $connectionPayload = [
                'waba_id' => $wabaData['waba_id'],
                'phone_number_id' => $wabaData['phone_number_id'],
                'phone_number' => $wabaData['phone_number'],
                'access_token' => $wabaData['access_token'],
                'status' => WhatsappConnection::STATUS_ACTIVE,
            ];

            // Re-activate an existing disconnected row so reconnect works.
            if ($existingConnection) {
                $existingConnection->update($connectionPayload);
                $connection = $existingConnection->fresh();
            } else {
                $connection = WhatsappConnection::create([
                    'account_id' => $account->id,
                    ...$connectionPayload,
                ]);
            }

            \Log::info('✅ WhatsappConnection record created', [
                'connection_id' => $connection->id,
                'account_id' => $account->id,
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
            \Log::error('❌ WhatsApp connection failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => [
                    'code' => 'EXCHANGE_FAILED',
                    'message' => 'Unable to connect WhatsApp: ' . $e->getMessage(),
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

    /**
     * List message templates for the account's WABA.
     */
    public function templates(Request $request): JsonResponse
    {
        $account = $request->user()->account;
        $connection = $account->whatsappConnection;

        if (!$connection || !$connection->isActive()) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_CONNECTED',
                    'message' => 'No active WhatsApp connection.',
                ],
            ], 404);
        }

        try {
            $templates = $this->whatsAppService->getTemplates($connection);

            return response()->json([
                'data' => $templates,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code' => 'FETCH_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Create a message template.
     */
    public function createTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'regex:/^[a-z0-9_]+$/'],
            'language' => ['required', 'string'],
            'category' => ['required', 'string', 'in:MARKETING,UTILITY,AUTHENTICATION'],
            'components' => ['required', 'array'],
        ]);

        $account = $request->user()->account;
        $connection = $account->whatsappConnection;

        if (!$connection || !$connection->isActive()) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_CONNECTED',
                    'message' => 'No active WhatsApp connection.',
                ],
            ], 404);
        }

        try {
            $result = $this->whatsAppService->createTemplate($connection, $validated);

            return response()->json([
                'data' => $result,
                'message' => 'Template created successfully. It will be reviewed by Meta.',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code' => 'CREATE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Delete a message template.
     */
    public function deleteTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string'],
        ]);

        $account = $request->user()->account;
        $connection = $account->whatsappConnection;

        if (!$connection || !$connection->isActive()) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_CONNECTED',
                    'message' => 'No active WhatsApp connection.',
                ],
            ], 404);
        }

        try {
            $this->whatsAppService->deleteTemplate($connection, $validated['name']);

            return response()->json([
                'message' => 'Template deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code' => 'DELETE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Send a template message to a phone number.
     * Creates a contact, conversation, and message record so it appears in the inbox.
     */
    public function sendTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => ['required', 'string'],
            'template_name' => ['required', 'string'],
            'language_code' => ['required', 'string'],
            'components' => ['sometimes', 'array'],
        ]);

        $account = $request->user()->account;
        $connection = $account->whatsappConnection;

        if (!$connection || !$connection->isActive()) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_CONNECTED',
                    'message' => 'No active WhatsApp connection.',
                ],
            ], 404);
        }

        try {
            // Send via WhatsApp API
            $result = $this->whatsAppService->sendTemplateMessage(
                $validated['phone_number'],
                $validated['template_name'],
                $validated['language_code'],
                $validated['components'] ?? [],
                $connection
            );

            // Create contact if not exists
            $phoneNumber = $validated['phone_number'];
            if (!str_starts_with($phoneNumber, '+')) {
                $phoneNumber = '+' . $phoneNumber;
            }

            $contact = Contact::firstOrCreate(
                [
                    'account_id' => $account->id,
                    'phone_number' => $phoneNumber,
                ],
                [
                    'name' => $phoneNumber,
                ]
            );

            // Get or create conversation
            $conversation = $this->conversationService->getOrCreateConversation($contact);

            // Create message record
            $message = Message::create([
                'account_id' => $account->id,
                'conversation_id' => $conversation->id,
                'direction' => Message::DIRECTION_OUTBOUND,
                'content' => "[Template: {$validated['template_name']}]",
                'status' => Message::STATUS_SENT,
                'whatsapp_message_id' => $result['message_id'] ?? null,
            ]);

            $conversation->update([
                'last_message_at' => now(),
                'conversation_started_at' => now(),
            ]);

            return response()->json([
                'data' => $result,
                'message' => 'Template message sent successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code' => 'SEND_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }
}
