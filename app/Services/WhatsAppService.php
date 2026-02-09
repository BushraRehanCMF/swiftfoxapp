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
     * Exchange access_token from Meta Embedded Signup for WABA info.
     * The access_token comes directly from FB.login() callback.
     *
     * @return array{waba_id: string, phone_number_id: string, phone_number: string}
     * @throws \Exception
     */
    public function exchangeAccessTokenForWabaInfo(string $accessToken): array
    {
        Log::info('🔄 Starting access_token processing', [
            'token_length' => strlen($accessToken),
            'token_preview' => substr($accessToken, 0, 30) . '...',
        ]);

        if (!$accessToken) {
            Log::error('❌ Empty access token provided');
            throw new \Exception('Access token is empty.');
        }

        // Get WABA info directly with the access token
        Log::info('📤 Fetching WABA info from Meta using access token');
        $meResponse = Http::withToken($accessToken)->get(
            "{$this->apiUrl}/v22.0/me",
            ['fields' => 'id,name,email,picture']
        );

        Log::info('📥 WABA info response', [
            'status' => $meResponse->status(),
            'is_successful' => $meResponse->successful(),
            'response_keys' => array_keys($meResponse->json()),
        ]);

        if (!$meResponse->successful()) {
            $errorData = $meResponse->json();
            Log::error('❌ Failed to fetch WABA info', [
                'status' => $meResponse->status(),
                'error' => $errorData['error'] ?? 'Unknown error',
                'full_response' => $errorData,
            ]);
            throw new \Exception('Failed to fetch WABA information: ' . ($errorData['error']['message'] ?? 'Unknown error'));
        }

        $meData = $meResponse->json();
        $wabaId = $meData['id'] ?? null;

        Log::info('✅ WABA ID extracted', [
            'waba_id' => $wabaId,
            'waba_name' => $meData['name'] ?? 'Unknown',
        ]);

        if (!$wabaId) {
            Log::error('❌ WABA ID not found', [
                'response_keys' => array_keys($meData),
                'full_response' => $meData,
            ]);
            throw new \Exception('WABA ID not found. Please ensure you have the correct permissions.');
        }

        // Get phone numbers for this WABA
        Log::info('📤 Fetching phone numbers for WABA', ['waba_id' => $wabaId]);
        $phoneResponse = Http::withToken($accessToken)->get(
            "{$this->apiUrl}/v22.0/{$wabaId}/phone_numbers",
            ['fields' => 'id,display_phone_number,phone_number_id,verified_name']
        );

        Log::info('📥 Phone numbers response', [
            'status' => $phoneResponse->status(),
            'is_successful' => $phoneResponse->successful(),
        ]);

        if (!$phoneResponse->successful()) {
            $errorData = $phoneResponse->json();
            Log::error('❌ Failed to fetch phone numbers', [
                'status' => $phoneResponse->status(),
                'error' => $errorData['error'] ?? 'Unknown error',
                'waba_id' => $wabaId,
            ]);
            throw new \Exception('Failed to fetch phone numbers: ' . ($errorData['error']['message'] ?? 'Unknown error'));
        }

        $phoneData = $phoneResponse->json();
        $phoneNumbers = $phoneData['data'] ?? [];

        Log::info('📥 Phone numbers extracted', [
            'count' => count($phoneNumbers),
            'phone_numbers' => array_map(
                fn($p) => ['phone_number_id' => $p['phone_number_id'] ?? null, 'display' => $p['display_phone_number'] ?? null],
                $phoneNumbers
            ),
        ]);

        if (empty($phoneNumbers)) {
            Log::error('❌ No phone numbers found', ['waba_id' => $wabaId]);
            throw new \Exception('No phone numbers found for this WhatsApp Business Account. Please add a phone number in Business Manager.');
        }

        // Use the first verified phone number
        $phoneNumber = null;
        foreach ($phoneNumbers as $p) {
            if (!empty($p['phone_number_id']) && !empty($p['display_phone_number'])) {
                $phoneNumber = $p;
                break;
            }
        }

        if (!$phoneNumber) {
            Log::error('❌ No valid phone number found', ['phone_numbers' => $phoneNumbers]);
            throw new \Exception('No valid phone number found in WhatsApp Business Account.');
        }

        $phoneNumberId = $phoneNumber['phone_number_id'];
        $displayPhoneNumber = $phoneNumber['display_phone_number'];

        Log::info('✅ Phone number selected', [
            'display_phone_number' => $displayPhoneNumber,
            'phone_number_id' => $phoneNumberId,
        ]);

        Log::info('✅ WhatsApp connection established successfully', [
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
