<?php

namespace App\Services;

use App\Models\WhatsappConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $apiVersion;
    protected string $apiUrl;

    public function __construct()
    {
        $this->apiVersion = config('swiftfox.whatsapp.api_version', 'v18.0');
        $this->apiUrl = config('swiftfox.whatsapp.api_url', 'https://graph.facebook.com');
    }

    /**
     * Send a text message via WhatsApp Cloud API.
     *
     * @return array{message_id: string}
     * @throws \Exception
     */
    public function sendTextMessage(string $phoneNumber, string $content, ?WhatsappConnection $connection = null): array
    {
        // Get the connection from the current account if not provided
        if (!$connection) {
            $connection = $this->getConnectionForCurrentAccount();
        }

        if (!$connection) {
            throw new \Exception('No WhatsApp connection found.');
        }

        $accessToken = config('swiftfox.whatsapp.access_token');

        if (!$accessToken) {
            // For development/testing, simulate success
            if (app()->environment('local', 'testing')) {
                return [
                    'message_id' => 'wamid_' . uniqid(),
                ];
            }
            throw new \Exception('WhatsApp access token not configured.');
        }

        $url = "{$this->apiUrl}/{$this->apiVersion}/{$connection->phone_number_id}/messages";

        $response = Http::withToken($accessToken)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $this->formatPhoneNumber($phoneNumber),
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $content,
                ],
            ]);

        if (!$response->successful()) {
            Log::error('WhatsApp API error', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            throw new \Exception('Failed to send WhatsApp message: ' . ($response->json()['error']['message'] ?? 'Unknown error'));
        }

        $data = $response->json();

        return [
            'message_id' => $data['messages'][0]['id'] ?? null,
        ];
    }

    /**
     * Format phone number for WhatsApp API (remove + and spaces).
     */
    protected function formatPhoneNumber(string $phoneNumber): string
    {
        return preg_replace('/[^0-9]/', '', $phoneNumber);
    }

    /**
     * Get WhatsApp connection for the current authenticated user's account.
     */
    protected function getConnectionForCurrentAccount(): ?WhatsappConnection
    {
        $user = auth()->user();

        if (!$user || !$user->hasAccount()) {
            return null;
        }

        return $user->account->whatsappConnection;
    }

    /**
     * Exchange input_token from Meta Embedded Signup for WABA info.
     * The input_token is a JWT from Meta's Embedded Signup modal.
     *
     * @return array{waba_id: string, phone_number_id: string, phone_number: string}
     * @throws \Exception
     */
    public function exchangeInputTokenForWabaInfo(string $inputToken): array
    {
        $appId = config('swiftfox.whatsapp.app_id');
        $appSecret = config('swiftfox.whatsapp.app_secret');

        Log::info('🔄 Starting input_token exchange', [
            'token_length' => strlen($inputToken),
            'token_preview' => substr($inputToken, 0, 20) . '...',
        ]);

        if (!$appId || !$appSecret) {
            Log::error('❌ Missing WhatsApp configuration', [
                'has_app_id' => !!$appId,
                'has_app_secret' => !!$appSecret,
            ]);
            throw new \Exception('Missing WHATSAPP_APP_ID or WHATSAPP_APP_SECRET configuration.');
        }

        // Exchange input_token for access token and WABA info
        Log::info('📤 Sending token exchange request to Meta', [
            'endpoint' => 'https://graph.instagram.com/v22.0/oauth/access_token',
            'client_id' => $appId,
        ]);

        $response = Http::asForm()->post("https://graph.instagram.com/v22.0/oauth/access_token", [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'input_token' => $inputToken,
            'access_token' => "{$appId}|{$appSecret}",
        ]);

        Log::info('📥 Token exchange response from Meta', [
            'status' => $response->status(),
            'has_access_token' => isset($response->json()['access_token']),
            'response_keys' => array_keys($response->json()),
        ]);

        if (!$response->successful()) {
            $errorData = $response->json();
            Log::error('❌ Meta token exchange failed', [
                'status' => $response->status(),
                'error' => $errorData['error'] ?? 'Unknown error',
                'full_response' => $errorData,
            ]);
            throw new \Exception('Failed to exchange input token: ' . ($errorData['error']['message'] ?? 'Unknown error'));
        }

        $data = $response->json();
        $userToken = $data['access_token'] ?? null;

        if (!$userToken) {
            Log::error('❌ No access token in response', ['response' => $data]);
            throw new \Exception('No access token returned from Meta.');
        }

        Log::info('✅ Access token obtained', [
            'token_preview' => substr($userToken, 0, 20) . '...',
        ]);

        // Get WABA info with the user token
        Log::info('📤 Fetching WABA info from Meta');
        $meResponse = Http::withToken($userToken)->get(
            "{$this->apiUrl}/v22.0/me",
            ['fields' => 'id,name']
        );

        Log::info('📥 WABA info response', [
            'status' => $meResponse->status(),
            'response_keys' => array_keys($meResponse->json()),
        ]);

        if (!$meResponse->successful()) {
            Log::error('❌ Failed to fetch WABA info', [
                'status' => $meResponse->status(),
                'response' => $meResponse->json(),
            ]);
            throw new \Exception('Failed to fetch WABA information from Meta.');
        }

        $meData = $meResponse->json();
        $wabaId = $meData['id'] ?? null;

        Log::info('✅ WABA ID extracted', ['waba_id' => $wabaId]);

        if (!$wabaId) {
            Log::error('❌ WABA ID not found', ['response' => $meData]);
            throw new \Exception('WABA ID not found in Meta response.');
        }

        // Get phone numbers for this WABA
        Log::info('📤 Fetching phone numbers for WABA', ['waba_id' => $wabaId]);
        $phoneResponse = Http::withToken($userToken)->get(
            "{$this->apiUrl}/v22.0/{$wabaId}/phone_numbers",
            ['fields' => 'id,display_phone_number,phone_number_id']
        );

        Log::info('📥 Phone numbers response', [
            'status' => $phoneResponse->status(),
            'response_keys' => array_keys($phoneResponse->json()),
        ]);

        if (!$phoneResponse->successful()) {
            Log::error('❌ Failed to fetch phone numbers', [
                'status' => $phoneResponse->status(),
                'response' => $phoneResponse->json(),
            ]);
            throw new \Exception('Failed to fetch phone numbers from Meta.');
        }

        $phoneData = $phoneResponse->json();
        $phoneNumbers = $phoneData['data'] ?? [];

        Log::info('📥 Phone numbers extracted', [
            'count' => count($phoneNumbers),
            'phone_numbers' => $phoneNumbers,
        ]);

        if (empty($phoneNumbers)) {
            Log::error('❌ No phone numbers found', ['waba_id' => $wabaId]);
            throw new \Exception('No phone numbers found for this WhatsApp Business Account.');
        }

        // Use the first phone number
        $phoneNumber = $phoneNumbers[0];
        $phoneNumberId = $phoneNumber['phone_number_id'] ?? null;
        $displayPhoneNumber = $phoneNumber['display_phone_number'] ?? null;

        Log::info('✅ Phone number selected', [
            'display_phone_number' => $displayPhoneNumber,
            'phone_number_id' => $phoneNumberId,
        ]);

        if (!$phoneNumberId || !$displayPhoneNumber) {
            Log::error('❌ Incomplete phone number data', ['phone_number' => $phoneNumber]);
            throw new \Exception('Incomplete phone number data from Meta.');
        }

        Log::info('✅ WhatsApp connection established via Embedded Signup', [
            'waba_id' => $wabaId,
            'phone_number' => $displayPhoneNumber,
        ]);

        return [
            'waba_id' => $wabaId,
            'phone_number_id' => $phoneNumberId,
            'phone_number' => $displayPhoneNumber,
        ];
    }

    /**
        $appSecret = config('swiftfox.whatsapp.app_secret');

        if (!$appSecret) {
            return false;
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Verify webhook challenge from Meta.
     */
    public function verifyWebhookChallenge(string $mode, string $token, string $challenge): ?string
    {
        $verifyToken = config('swiftfox.whatsapp.webhook_verify_token');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            return $challenge;
        }

        return null;
    }
}
