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
            'has_redirect_uri' => (bool) $request->input('redirect_uri'),
        ]);

        $validator = Validator::make($request->all(), [
            'code' => ['sometimes', 'nullable', 'string'],
            'access_token' => ['sometimes', 'nullable', 'string'],
            'is_input_token' => ['sometimes', 'boolean'],
            'redirect_uri' => ['sometimes', 'nullable', 'string'],
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
                'error' => 'Validation failed. Check server logs for details.',
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
                'error' => 'Provide either code or access_token. (Request received)',
            ]);
        }

        $isInputToken = $validated['is_input_token'] ?? false;
        $redirectUri = $validated['redirect_uri'] ?? null;
        $accessToken = $validated['access_token'] ?? null;

        try {
            \Log::info('WhatsApp test starting exchange', [
                'token_type' => $accessToken ? 'access_token' : ($isInputToken ? 'input_token' : 'code'),
                'has_redirect_uri' => (bool) $redirectUri,
            ]);

            $wabaData = $accessToken
                ? $this->whatsAppService->exchangeAccessTokenForWabaInfo($accessToken)
                : $this->whatsAppService->exchangeCodeForWabaInfo(
                    $validated['code'],
                    $isInputToken,
                    $redirectUri
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
