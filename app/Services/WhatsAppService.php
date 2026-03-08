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
     * Exchange authorization code/input_token from Meta Embedded Signup for WABA info.
     *
     * For Embedded Signup:
     * - If input_token (JWT) is provided, use it directly to fetch WABA info
     * - If code is provided, exchange it for access token first (standard OAuth)
     *
     * @return array{waba_id: string, phone_number_id: string, phone_number: string}
     * @throws \Exception
     */
    public function exchangeCodeForWabaInfo(
        string $code,
        bool $isInputToken = false,
        ?string $redirectUriOverride = null
    ): array
    {
        $appId = config('swiftfox.whatsapp.app_id');
        $appSecret = config('swiftfox.whatsapp.app_secret');

        Log::info('🔄 Starting authorization exchange', [
            'token_type' => $isInputToken ? 'input_token (JWT)' : 'authorization_code',
            'token_preview' => substr($code, 0, 30) . '...',
        ]);

        if (!$appId || !$appSecret) {
            Log::error('❌ Missing WhatsApp configuration', [
                'has_app_id' => !!$appId,
                'has_app_secret' => !!$appSecret,
            ]);
            throw new \Exception('Missing WHATSAPP_APP_ID or WHATSAPP_APP_SECRET configuration.');
        }

        $accessToken = $code; // Start with the token provided

        // If it's an authorization code, exchange it for an access token first
        if (!$isInputToken) {
            $configuredRedirectUri = config('swiftfox.whatsapp.redirect_uri');
            $redirectUri = $redirectUriOverride
                ?: ($configuredRedirectUri ?: rtrim(config('app.url'), '/') . '/whatsapp');

            Log::info('WhatsApp OAuth Debug', [
                'code_preview' => substr($code, 0, 30) . '...',
                'redirect_uri_being_sent' => $redirectUri,
                'client_id' => $appId,
            ]);

            Log::info('📤 Exchanging authorization code for access token', [
                'endpoint' => 'https://graph.facebook.com/v22.0/oauth/access_token',
                'redirect_uri' => $redirectUri,
            ]);

            $tokenResponse = Http::asForm()->post('https://graph.facebook.com/v22.0/oauth/access_token', [
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirectUri,
            ]);

            Log::info('Token Response', [
                'status' => $tokenResponse->status(),
                'body' => $tokenResponse->json(),
            ]);

            Log::info('📥 Token exchange response', [
                'status' => $tokenResponse->status(),
                'is_successful' => $tokenResponse->successful(),
                'response_keys' => array_keys($tokenResponse->json()),
            ]);

            if (!$tokenResponse->successful()) {
                $errorData = $tokenResponse->json();
                Log::error('❌ Code exchange failed', [
                    'status' => $tokenResponse->status(),
                    'error_response' => $errorData,
                    'error_message' => $errorData['error']['message'] ?? ($errorData['error'] ?? 'Unknown error'),
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
        } else {
            Log::info('✅ Using input_token directly (JWT from Embedded Signup)', [
                'token_preview' => substr($code, 0, 30) . '...',
            ]);
        }

        return $this->fetchWabaInfoWithAccessToken($accessToken);
    }

    /**
     * Exchange a user access token for WABA info.
     *
     * @return array{waba_id: string, phone_number_id: string, phone_number: string}
     * @throws \Exception
     */
    public function exchangeAccessTokenForWabaInfo(string $accessToken): array
    {
        Log::info('🔄 Using access token directly for WABA lookup', [
            'token_preview' => substr($accessToken, 0, 30) . '...',
        ]);

        return $this->fetchWabaInfoWithAccessToken($accessToken);
    }

    /**
     * Verify WABA access and fetch phone number details using user access token.
     *
     * This method is used when the Embedded Signup returns waba_id and phone_number_id directly.
     * We use the USER'S access token (not system token) to verify they have access to the WABA.
     *
     * @param string $userAccessToken The access_token from Embedded Signup flow
     * @param string $wabaId The WABA ID returned from Embedded Signup
     * @param string $phoneNumberId The phone number ID returned from Embedded Signup
     * @return array{waba_id: string, phone_number_id: string, phone_number: string}
     * @throws \Exception
     */
    public function verifyAndFetchWabaInfo(
        string $userAccessToken,
        string $wabaId,
        string $phoneNumberId
    ): array {
        Log::info('🔍 Verifying WABA access with user access token', [
            'token_preview' => substr($userAccessToken, 0, 30) . '...',
            'waba_id' => $wabaId,
            'phone_number_id' => $phoneNumberId,
        ]);

        // CRITICAL: Use the USER'S access token to verify access to the WABA
        // DO NOT use system user token here - it will fail with (#100) Missing Permission
        $wabaResponse = Http::withToken($userAccessToken)->get(
            "{$this->apiUrl}/v22.0/{$wabaId}",
            ['fields' => 'id,name']
        );

        if (!$wabaResponse->successful()) {
            $errorData = $wabaResponse->json();
            Log::error('❌ Failed to verify WABA access with user token', [
                'status' => $wabaResponse->status(),
                'error_response' => $errorData,
                'waba_id' => $wabaId,
            ]);
            throw new \Exception(
                'Failed to verify WABA access: ' . ($errorData['error']['message'] ?? 'Permission denied')
            );
        }

        Log::info('✅ WABA access verified with user token');

        // Fetch phone number details to get display_phone_number
        Log::info('📤 Fetching phone number details', ['phone_number_id' => $phoneNumberId]);
        $phoneResponse = Http::withToken($userAccessToken)->get(
            "{$this->apiUrl}/v22.0/{$phoneNumberId}",
            ['fields' => 'id,display_phone_number,verified_name']
        );

        if (!$phoneResponse->successful()) {
            $errorData = $phoneResponse->json();
            Log::error('❌ Failed to fetch phone number details', [
                'status' => $phoneResponse->status(),
                'error_response' => $errorData,
                'phone_number_id' => $phoneNumberId,
            ]);
            throw new \Exception(
                'Failed to fetch phone number: ' . ($errorData['error']['message'] ?? 'Unknown error')
            );
        }

        $phoneData = $phoneResponse->json();
        $displayPhoneNumber = $phoneData['display_phone_number'] ?? null;

        if (!$displayPhoneNumber) {
            Log::error('❌ No display_phone_number in response', ['response' => $phoneData]);
            throw new \Exception('Phone number not found in response.');
        }

        Log::info('✅ Phone number details fetched', [
            'display_phone_number' => $displayPhoneNumber,
        ]);

        Log::info('✅ WhatsApp connection verified successfully', [
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
     * Fetch WABA and phone number data using an access token.
     *
     * @return array{waba_id: string, phone_number_id: string, phone_number: string}
     * @throws \Exception
     */
    protected function fetchWabaInfoWithAccessToken(string $accessToken): array
    {
        // Step 2: Get businesses linked to the user
        Log::info('📤 Fetching businesses for user from Meta');
        $meResponse = Http::withToken($accessToken)->get(
            "{$this->apiUrl}/v22.0/me",
            ['fields' => 'id,name,businesses']
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
                'error_response' => $errorData,
            ]);
            throw new \Exception('Failed to fetch WABA information: ' . ($errorData['error']['message'] ?? 'Unknown error'));
        }

        $meData = $meResponse->json();
        $businesses = $meData['businesses']['data'] ?? [];
        $businessId = $businesses[0]['id'] ?? null;

        Log::info('✅ Business ID extracted', [
            'business_id' => $businessId,
        ]);

        if (!$businessId) {
            Log::error('❌ Business ID not found', ['response' => $meData]);
            throw new \Exception('Business ID not found. Ensure the user has access to a Business Manager.');
        }

        // Step 3: Get WABA accounts owned by the business
        Log::info('📤 Fetching owned WhatsApp Business Accounts', [
            'business_id' => $businessId,
        ]);
        $wabaResponse = Http::withToken($accessToken)->get(
            "{$this->apiUrl}/v22.0/{$businessId}/owned_whatsapp_business_accounts",
            ['fields' => 'id,name']
        );

        Log::info('📥 WABA list response', [
            'status' => $wabaResponse->status(),
            'is_successful' => $wabaResponse->successful(),
        ]);

        if (!$wabaResponse->successful()) {
            $errorData = $wabaResponse->json();
            Log::error('❌ Failed to fetch WABA list', [
                'status' => $wabaResponse->status(),
                'error_response' => $errorData,
            ]);
            throw new \Exception('Failed to fetch WABA list: ' . ($errorData['error']['message'] ?? 'Unknown error'));
        }

        $wabaList = $wabaResponse->json();
        $wabaData = $wabaList['data'] ?? [];
        $wabaId = $wabaData[0]['id'] ?? null;

        Log::info('✅ WABA ID extracted', [
            'waba_id' => $wabaId,
        ]);

        if (!$wabaId) {
            Log::error('❌ WABA ID not found', ['response' => $wabaList ?? $meData]);
            throw new \Exception('WABA ID not found. Ensure the business has a WhatsApp Business Account.');
        }

        // Step 4: Get phone numbers for this WABA
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
                'error_response' => $errorData,
            ]);
            throw new \Exception('Failed to fetch phone numbers: ' . ($errorData['error']['message'] ?? 'Unknown error'));
        }

        $phoneData = $phoneResponse->json();
        $phoneNumbers = $phoneData['data'] ?? [];

        Log::info('📥 Phone numbers extracted', [
            'count' => count($phoneNumbers),
        ]);

        if (empty($phoneNumbers)) {
            Log::error('❌ No phone numbers found', ['waba_id' => $wabaId]);
            throw new \Exception('No phone numbers found. Please add a phone number in Business Manager.');
        }

        // Use the first valid phone number
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
