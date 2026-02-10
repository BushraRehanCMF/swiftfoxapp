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
            'code' => ['required', 'string'],
            'is_input_token' => ['sometimes', 'boolean'],
            'redirect_uri' => ['sometimes', 'string'],
        ]);

        $isInputToken = $validated['is_input_token'] ?? false;
        $redirectUri = $validated['redirect_uri'] ?? null;

        try {
            $wabaData = $this->whatsAppService->exchangeCodeForWabaInfo(
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
