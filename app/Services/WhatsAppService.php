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
     * Exchange authorization code from Meta Embedded Signup for WABA info.
     *
     * @return array{waba_id: string, phone_number_id: string, phone_number: string, access_token: string}
     * @throws \Exception
     */
    public function exchangeCodeForWabaInfo(string $code): array
    {
        $appId = config('swiftfox.whatsapp.app_id');
        $appSecret = config('swiftfox.whatsapp.app_secret');

        if (!$appId || !$appSecret) {
            throw new \Exception('Missing WHATSAPP_APP_ID or WHATSAPP_APP_SECRET configuration.');
        }

        // Step 1: Exchange code for access token
        $redirectUri = config('app.url') . '/whatsapp';

        $tokenResponse = Http::post("https://graph.facebook.com/v22.0/oauth/access_token", [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]);

        if (!$tokenResponse->successful()) {
            Log::error('Meta OAuth token exchange failed', [
                'status' => $tokenResponse->status(),
                'body' => $tokenResponse->json(),
            ]);
            throw new \Exception('Failed to exchange code for access token.');
        }

        $tokenData = $tokenResponse->json();
        $accessToken = $tokenData['access_token'] ?? null;

        if (!$accessToken) {
            throw new \Exception('No access token returned from Meta.');
        }

        // Step 2: Get WABA (WhatsApp Business Account) info
        $meResponse = Http::withToken($accessToken)->get(
            "{$this->apiUrl}/v22.0/me",
            ['fields' => 'id,name']
        );

        if (!$meResponse->successful()) {
            throw new \Exception('Failed to fetch WABA information from Meta.');
        }

        $meData = $meResponse->json();
        $wabaId = $meData['id'] ?? null;

        if (!$wabaId) {
            throw new \Exception('WABA ID not found in Meta response.');
        }

        // Step 3: Get phone numbers for this WABA
        $phoneResponse = Http::withToken($accessToken)->get(
            "{$this->apiUrl}/v22.0/{$wabaId}/phone_numbers",
            ['fields' => 'id,display_phone_number,phone_number_id']
        );

        if (!$phoneResponse->successful()) {
            throw new \Exception('Failed to fetch phone numbers from Meta.');
        }

        $phoneData = $phoneResponse->json();
        $phoneNumbers = $phoneData['data'] ?? [];

        if (empty($phoneNumbers)) {
            throw new \Exception('No phone numbers found for this WhatsApp Business Account.');
        }

        // Use the first phone number (in production, might want to let user choose)
        $phoneNumber = $phoneNumbers[0];
        $phoneNumberId = $phoneNumber['phone_number_id'] ?? null;
        $extractedPhoneNumber = $phoneNumber['display_phone_number'] ?? null;

        if (!$phoneNumberId || !$extractedPhoneNumber) {
            throw new \Exception('Incomplete phone number data from Meta.');
        }

        Log::info('WhatsApp connection exchanged successfully', [
            'waba_id' => $wabaId,
            'phone_number' => $extractedPhoneNumber,
        ]);

        return [
            'waba_id' => $wabaId,
            'phone_number_id' => $phoneNumberId,
            'phone_number' => $extractedPhoneNumber,
            'access_token' => $accessToken,
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
