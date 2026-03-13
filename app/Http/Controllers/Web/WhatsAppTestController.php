<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WhatsAppTestController extends Controller
{
    public function __construct(
        protected WhatsAppService $whatsAppService
    ) {}

    public function show()
    {
        return view('whatsapp-test', [
            'appId' => config('swiftfox.whatsapp.app_id'),
            'configId' => config('swiftfox.whatsapp.config_id'),
            'redirectUri' => config('swiftfox.whatsapp.redirect_uri')
                ?: rtrim(config('app.url'), '/') . '/whatsapp',
            'result' => null,
            'error' => null,
        ]);
    }

    public function connect(Request $request)
    {
        \Log::info('WhatsApp test connect hit', [
            'path' => $request->path(),
            'method' => $request->method(),
            'has_code' => (bool) $request->input('code'),
            'has_access_token' => (bool) $request->input('access_token'),
            'has_waba_id' => (bool) $request->input('waba_id'),
            'has_phone_number_id' => (bool) $request->input('phone_number_id'),
        ]);

        $validator = Validator::make($request->all(), [
            'code' => ['sometimes', 'nullable', 'string'],
            'access_token' => ['sometimes', 'nullable', 'string'],
            'waba_id' => ['required', 'string'],
            'phone_number_id' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            \Log::warning('WhatsApp test validation failed', [
                'errors' => $validator->errors()->toArray(),
            ]);

            return view('whatsapp-test', [
                'appId' => config('swiftfox.whatsapp.app_id'),
                'configId' => config('swiftfox.whatsapp.config_id'),
                'redirectUri' => config('swiftfox.whatsapp.redirect_uri')
                    ?: rtrim(config('app.url'), '/') . '/whatsapp',
                'result' => null,
                'error' => 'Validation failed: waba_id and phone_number_id are required.',
            ]);
        }

        $validated = $validator->validated();

        if (empty($validated['code']) && empty($validated['access_token'])) {
            return view('whatsapp-test', [
                'appId' => config('swiftfox.whatsapp.app_id'),
                'configId' => config('swiftfox.whatsapp.config_id'),
                'redirectUri' => config('swiftfox.whatsapp.redirect_uri')
                    ?: rtrim(config('app.url'), '/') . '/whatsapp',
                'result' => null,
                'error' => 'Provide either code or access_token.',
            ]);
        }

        $code = $validated['code'] ?? null;
        $accessToken = $validated['access_token'] ?? null;
        $wabaId = $validated['waba_id'];
        $phoneNumberId = $validated['phone_number_id'];

        try {
            // Exchange code for access token if needed
            if ($code) {
                \Log::info('WhatsApp test: exchanging code for access token');
                $accessToken = $this->whatsAppService->exchangeCodeForAccessToken($code);
            }

            // Trust waba_id and phone_number_id, just fetch display phone number
            \Log::info('WhatsApp test: processing Embedded Signup result');
            $wabaData = $this->whatsAppService->processEmbeddedSignup(
                $accessToken,
                $wabaId,
                $phoneNumberId
            );

            \Log::info('WhatsApp test exchange success', [
                'waba_id' => $wabaData['waba_id'] ?? null,
                'phone_number' => $wabaData['phone_number'] ?? null,
            ]);

            return view('whatsapp-test', [
                'appId' => config('swiftfox.whatsapp.app_id'),
                'configId' => config('swiftfox.whatsapp.config_id'),
                'redirectUri' => config('swiftfox.whatsapp.redirect_uri')
                    ?: rtrim(config('app.url'), '/') . '/whatsapp',
                'result' => $wabaData,
                'error' => null,
            ]);
        } catch (\Exception $e) {
            \Log::error('WhatsApp test exchange failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return view('whatsapp-test', [
                'appId' => config('swiftfox.whatsapp.app_id'),
                'configId' => config('swiftfox.whatsapp.config_id'),
                'redirectUri' => config('swiftfox.whatsapp.redirect_uri')
                    ?: rtrim(config('app.url'), '/') . '/whatsapp',
                'result' => null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
