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

        $accessToken = $connection->access_token;

        if (!$accessToken) {
            // For development/testing, simulate success
            if (app()->environment('local', 'testing')) {
                return [
                    'message_id' => 'wamid_' . uniqid(),
                ];
            }
            throw new \Exception('No access token stored for this WhatsApp connection.');
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
     * Exchange an authorization code for an access token only (no WABA lookup).
     *
     * @throws \Exception
     */
    public function exchangeCodeForAccessToken(string $code, ?string $redirectUriOverride = null, bool $fromFbLogin = false): string
    {
        $appId = config('swiftfox.whatsapp.app_id');
        $appSecret = config('swiftfox.whatsapp.app_secret');

        if (!$appId || !$appSecret) {
            throw new \Exception('Missing WHATSAPP_APP_ID or WHATSAPP_APP_SECRET configuration.');
        }

        $params = [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
        ];

        // When code comes from FB.login() (Embedded Signup), do NOT send redirect_uri.
        // FB.login() codes are not tied to a redirect_uri and Meta will reject the mismatch.
        // Only include redirect_uri for traditional OAuth redirect flows.
        if (!$fromFbLogin) {
            $configuredRedirectUri = config('swiftfox.whatsapp.redirect_uri');
            $redirectUri = $redirectUriOverride
                ?: ($configuredRedirectUri ?: rtrim(config('app.url'), '/') . '/whatsapp');
            $params['redirect_uri'] = $redirectUri;
        }

        Log::info('📤 Exchanging code for access token', [
            'from_fb_login' => $fromFbLogin,
            'has_redirect_uri' => isset($params['redirect_uri']),
        ]);

        $tokenResponse = Http::asForm()->post('https://graph.facebook.com/v22.0/oauth/access_token', $params);

        if (!$tokenResponse->successful()) {
            $errorData = $tokenResponse->json();
            throw new \Exception('Failed to exchange code: ' . ($errorData['error']['message'] ?? $errorData['error'] ?? 'Unknown error'));
        }

        $accessToken = $tokenResponse->json()['access_token'] ?? null;
        if (!$accessToken) {
            throw new \Exception('No access token returned from Meta.');
        }

        return $accessToken;
    }

    /**
     * Exchange a short-lived user access token for a long-lived one (60 days).
     *
     * @throws \Exception
     */
    public function exchangeForLongLivedToken(string $shortLivedToken): string
    {
        $appId = config('swiftfox.whatsapp.app_id');
        $appSecret = config('swiftfox.whatsapp.app_secret');

        if (!$appId || !$appSecret) {
            throw new \Exception('Missing WHATSAPP_APP_ID or WHATSAPP_APP_SECRET configuration.');
        }

        Log::info('📤 Exchanging short-lived token for long-lived token');

        $response = Http::get("{$this->apiUrl}/v22.0/oauth/access_token", [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'fb_exchange_token' => $shortLivedToken,
        ]);

        if (!$response->successful()) {
            $errorData = $response->json();
            Log::warning('⚠️ Long-lived token exchange failed, using short-lived token as fallback', [
                'status' => $response->status(),
                'error' => $errorData['error']['message'] ?? 'Unknown',
            ]);
            return $shortLivedToken;
        }

        $longLivedToken = $response->json()['access_token'] ?? null;
        if (!$longLivedToken) {
            Log::warning('⚠️ No long-lived token in response, using short-lived token as fallback');
            return $shortLivedToken;
        }

        $expiresIn = $response->json()['expires_in'] ?? null;
        Log::info('✅ Long-lived token obtained', [
            'expires_in_seconds' => $expiresIn,
            'expires_in_days' => $expiresIn ? round($expiresIn / 86400, 1) : null,
        ]);

        return $longLivedToken;
    }

    /**
     * Get the System User access token from config.
     * This is a permanent token that never expires.
     */
    protected function getSystemUserToken(): ?string
    {
        return config('swiftfox.whatsapp.access_token');
    }

    /**
     * Process Embedded Signup result using session info (waba_id, phone_number_id) directly.
     * Uses the System User token (permanent, from .env) instead of the user's short-lived token.
     *
     * @return array{waba_id: string, phone_number_id: string, phone_number: string, access_token: string}
     * @throws \Exception
     */
    public function processEmbeddedSignup(string $accessToken, string $wabaId, string $phoneNumberId): array
    {
        Log::info('📤 Processing Embedded Signup with session info', [
            'waba_id' => $wabaId,
            'phone_number_id' => $phoneNumberId,
        ]);

        // Use the permanent System User token instead of the short-lived user token.
        // The System User token never expires and has full access to all shared WABAs.
        $systemUserToken = $this->getSystemUserToken();
        if ($systemUserToken) {
            Log::info('✅ Using System User token (permanent) instead of user token');
            $accessToken = $systemUserToken;
        } else {
            // Fallback: exchange short-lived token for long-lived token (60 days)
            Log::warning('⚠️ No System User token configured, falling back to long-lived token exchange');
            $accessToken = $this->exchangeForLongLivedToken($accessToken);
        }

        // Fetch the display phone number for this phone_number_id
        $phoneResponse = Http::withToken($accessToken)->get(
            "{$this->apiUrl}/v22.0/{$phoneNumberId}",
            ['fields' => 'display_phone_number,verified_name']
        );

        $displayPhoneNumber = $phoneNumberId; // fallback
        if ($phoneResponse->successful()) {
            $phoneData = $phoneResponse->json();
            $displayPhoneNumber = $phoneData['display_phone_number'] ?? $phoneNumberId;
            Log::info('✅ Phone number details fetched', [
                'display_phone_number' => $displayPhoneNumber,
                'verified_name' => $phoneData['verified_name'] ?? null,
            ]);
        } else {
            Log::warning('⚠️ Could not fetch phone number details, using ID as fallback', [
                'status' => $phoneResponse->status(),
                'error' => $phoneResponse->json()['error']['message'] ?? 'Unknown',
            ]);
        }

        Log::info('✅ Embedded Signup processed successfully', [
            'waba_id' => $wabaId,
            'phone_number' => $displayPhoneNumber,
        ]);

        return [
            'waba_id' => $wabaId,
            'phone_number_id' => $phoneNumberId,
            'phone_number' => $displayPhoneNumber,
            'access_token' => $accessToken,
        ];
    }

    /**
     * Process Embedded Signup with an authorization code + session info.
     * Exchanges the code first, then uses session info directly.
     *
     * @return array{waba_id: string, phone_number_id: string, phone_number: string, access_token: string}
     * @throws \Exception
     */
    public function processEmbeddedSignupWithCode(string $code, string $wabaId, string $phoneNumberId, ?string $redirectUri = null): array
    {
        // Code from FB.login() Embedded Signup - no redirect_uri needed
        $accessToken = $this->exchangeCodeForAccessToken($code, $redirectUri, fromFbLogin: true);
        return $this->processEmbeddedSignup($accessToken, $wabaId, $phoneNumberId);
    }

    /**
     * Exchange authorization code/input_token from Meta Embedded Signup for WABA info.
     *
     * For Embedded Signup:
     * - If input_token (JWT) is provided, use it directly to fetch WABA info
     * - If code is provided, exchange it for access token first (standard OAuth)
     *
     * @return array{waba_id: string, phone_number_id: string, phone_number: string, access_token: string}
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
     * @return array{waba_id: string, phone_number_id: string, phone_number: string, access_token: string}
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
     * Fetch WABA and phone number data using an access token.
     *
     * @return array{waba_id: string, phone_number_id: string, phone_number: string, access_token: string}
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
            'access_token' => $accessToken,
        ];
    }

    /**
     * Verify webhook signature from Meta.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
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

    /**
     * List message templates for a WABA.
     *
     * @return array
     * @throws \Exception
     */
    public function getTemplates(WhatsappConnection $connection): array
    {
        $accessToken = $connection->access_token;
        if (!$accessToken) {
            throw new \Exception('No access token for this WhatsApp connection.');
        }

        $url = "{$this->apiUrl}/{$this->apiVersion}/{$connection->waba_id}/message_templates";

        $response = Http::withToken($accessToken)->get($url, [
            'fields' => 'name,status,language,category,components',
            'limit' => 100,
        ]);

        if (!$response->successful()) {
            Log::error('Failed to fetch WhatsApp templates', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            throw new \Exception('Failed to fetch templates: ' . ($response->json()['error']['message'] ?? 'Unknown error'));
        }

        return $response->json()['data'] ?? [];
    }

    /**
     * Create a message template for a WABA.
     *
     * @return array
     * @throws \Exception
     */
    public function createTemplate(WhatsappConnection $connection, array $templateData): array
    {
        $accessToken = $connection->access_token;
        if (!$accessToken) {
            throw new \Exception('No access token for this WhatsApp connection.');
        }

        $url = "{$this->apiUrl}/{$this->apiVersion}/{$connection->waba_id}/message_templates";

        $payload = [
            'name' => $templateData['name'],
            'language' => $templateData['language'],
            'category' => $templateData['category'],
            'components' => $templateData['components'],
        ];

        if (!empty($templateData['allow_category_change'])) {
            $payload['allow_category_change'] = true;
        }

        Log::info('Creating WhatsApp template', [
            'waba_id' => $connection->waba_id,
            'name' => $templateData['name'],
            'category' => $templateData['category'],
        ]);

        $response = Http::withToken($accessToken)->post($url, $payload);

        if (!$response->successful()) {
            Log::error('Failed to create WhatsApp template', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            throw new \Exception('Failed to create template: ' . ($response->json()['error']['message'] ?? 'Unknown error'));
        }

        return $response->json();
    }

    /**
     * Delete a message template.
     *
     * @throws \Exception
     */
    public function deleteTemplate(WhatsappConnection $connection, string $templateName): array
    {
        $accessToken = $connection->access_token;
        if (!$accessToken) {
            throw new \Exception('No access token for this WhatsApp connection.');
        }

        $url = "{$this->apiUrl}/{$this->apiVersion}/{$connection->waba_id}/message_templates";

        $response = Http::withToken($accessToken)->delete($url, [
            'name' => $templateName,
        ]);

        if (!$response->successful()) {
            Log::error('Failed to delete WhatsApp template', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            throw new \Exception('Failed to delete template: ' . ($response->json()['error']['message'] ?? 'Unknown error'));
        }

        return $response->json();
    }

    /**
     * Send a template message via WhatsApp Cloud API.
     *
     * @return array{message_id: string}
     * @throws \Exception
     */
    public function sendTemplateMessage(
        string $phoneNumber,
        string $templateName,
        string $languageCode,
        array $components = [],
        ?WhatsappConnection $connection = null
    ): array {
        if (!$connection) {
            $connection = $this->getConnectionForCurrentAccount();
        }

        if (!$connection) {
            throw new \Exception('No WhatsApp connection found.');
        }

        $accessToken = $connection->access_token;
        if (!$accessToken) {
            throw new \Exception('No access token stored for this WhatsApp connection.');
        }

        $url = "{$this->apiUrl}/{$this->apiVersion}/{$connection->phone_number_id}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhoneNumber($phoneNumber),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode,
                ],
            ],
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        Log::info('Sending template message', [
            'to' => $this->formatPhoneNumber($phoneNumber),
            'template' => $templateName,
            'language' => $languageCode,
        ]);

        $response = Http::withToken($accessToken)->post($url, $payload);

        if (!$response->successful()) {
            Log::error('WhatsApp template API error', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            throw new \Exception('Failed to send template message: ' . ($response->json()['error']['message'] ?? 'Unknown error'));
        }

        $data = $response->json();

        return [
            'message_id' => $data['messages'][0]['id'] ?? null,
        ];
    }
}
