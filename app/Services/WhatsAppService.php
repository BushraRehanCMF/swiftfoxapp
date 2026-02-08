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
}
