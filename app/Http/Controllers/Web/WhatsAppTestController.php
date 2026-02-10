<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

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
        $validated = $request->validate([
            'code' => ['sometimes', 'string'],
            'access_token' => ['sometimes', 'string'],
            'is_input_token' => ['sometimes', 'boolean'],
            'redirect_uri' => ['sometimes', 'string'],
        ]);

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

        $isInputToken = $validated['is_input_token'] ?? false;
        $redirectUri = $validated['redirect_uri'] ?? null;
        $accessToken = $validated['access_token'] ?? null;

        try {
            $wabaData = $accessToken
                ? $this->whatsAppService->exchangeAccessTokenForWabaInfo($accessToken)
                : $this->whatsAppService->exchangeCodeForWabaInfo(
                    $validated['code'],
                    $isInputToken,
                    $redirectUri
                );

            return view('whatsapp-test', [
                'appId' => config('swiftfox.whatsapp.app_id'),
                'configId' => config('swiftfox.whatsapp.config_id'),
                'redirectUri' => config('swiftfox.whatsapp.redirect_uri')
                    ?: rtrim(config('app.url'), '/') . '/whatsapp',
                'result' => $wabaData,
                'error' => null,
            ]);
        } catch (\Exception $e) {
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
