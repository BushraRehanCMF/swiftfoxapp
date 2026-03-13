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
        $this->apiVersion = config('swiftfox.whatsapp.api_version', 'v25.0');
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
     * Exchange authorization code from Meta Embedded Signup for an access token.
     *
     * @throws \Exception
     */
    public function exchangeCodeForAccessToken(
        string $code,
        ?string $redirectUriOverride = null
    ): string {
        $appId = config('swiftfox.whatsapp.app_id');
        $appSecret = config('swiftfox.whatsapp.app_secret');

        Log::info('🔄 Starting code → access_token exchange', [
            'code_preview' => substr($code, 0, 30) . '...',
        ]);

        if (!$appId || !$appSecret) {
            Log::error('❌ Missing WhatsApp configuration', [
                'has_app_id' => !!$appId,
                'has_app_secret' => !!$appSecret,
            ]);
            throw new \Exception('Missing WHATSAPP_APP_ID or WHATSAPP_APP_SECRET configuration.');
        }

        $configuredRedirectUri = config('swiftfox.whatsapp.redirect_uri');
        $redirectUri = $redirectUriOverride
            ?: ($configuredRedirectUri ?: rtrim(config('app.url'), '/') . '/whatsapp');

        Log::info('📤 Exchanging authorization code for access token', [
            'redirect_uri' => $redirectUri,
            'client_id' => $appId,
        ]);

        $tokenResponse = Http::asForm()->post('https://graph.facebook.com/v22.0/oauth/access_token', [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]);

        Log::info('📥 Token exchange response', [
            'status' => $tokenResponse->status(),
            'is_successful' => $tokenResponse->successful(),
            'response_keys' => array_keys($tokenResponse->json() ?? []),
        ]);

        if (!$tokenResponse->successful()) {
            $errorData = $tokenResponse->json();
            Log::error('❌ Code exchange failed', [
                'status' => $tokenResponse->status(),
                'error_response' => $errorData,
            ]);
            throw new \Exception('Failed to exchange code: ' . ($errorData['error']['message'] ?? $errorData['error'] ?? 'Unknown error'));
        }

        $tokenData = $tokenResponse->json();
        $accessToken = $tokenData['access_token'] ?? null;

        if (!$accessToken) {
            Log::error('❌ No access token in response', ['response' => $tokenData]);
            throw new \Exception('No access token returned from Meta.');
        }

        Log::info('✅ Access token obtained', [
            'token_preview' => substr($accessToken, 0, 30) . '...',
        ]);

        return $accessToken;
    }

    /**
     * Process Embedded Signup result.
     *
     * After Embedded Signup, Meta returns waba_id + phone_number_id directly via the JS SDK.
     * We trust those values (Meta already assigned our app to the WABA).
     * We only fetch the display phone number for storage.
     *
     * @return array{waba_id: string, phone_number_id: string, phone_number: string}
     * @throws \Exception
     */
    public function processEmbeddedSignup(
        string $accessToken,
        string $wabaId,
        string $phoneNumberId
    ): array {
        Log::info('📋 Processing Embedded Signup result', [
            'token_preview' => substr($accessToken, 0, 30) . '...',
            'waba_id' => $wabaId,
            'phone_number_id' => $phoneNumberId,
        ]);

        // Fetch the display phone number using the user's access token
        Log::info('📤 Fetching display phone number', ['phone_number_id' => $phoneNumberId]);

        $phoneResponse = Http::withToken($accessToken)->get(
            "{$this->apiUrl}/v22.0/{$phoneNumberId}",
            ['fields' => 'id,display_phone_number,verified_name']
        );

        Log::info('📥 Phone number response', [
            'status' => $phoneResponse->status(),
            'is_successful' => $phoneResponse->successful(),
            'body' => $phoneResponse->json(),
        ]);

        $displayPhoneNumber = $phoneNumberId; // fallback

        if ($phoneResponse->successful()) {
            $phoneData = $phoneResponse->json();
            $displayPhoneNumber = $phoneData['display_phone_number'] ?? $phoneNumberId;
            Log::info('✅ Display phone number fetched', [
                'display_phone_number' => $displayPhoneNumber,
                'verified_name' => $phoneData['verified_name'] ?? null,
            ]);
        } else {
            // Non-fatal: we still have phone_number_id which is enough to send messages
            Log::warning('⚠️ Could not fetch display phone number, using phone_number_id as fallback', [
                'status' => $phoneResponse->status(),
                'error' => $phoneResponse->json()['error'] ?? null,
            ]);
        }

        Log::info('✅ Embedded Signup processed successfully', [
            'waba_id' => $wabaId,
            'phone_number_id' => $phoneNumberId,
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
