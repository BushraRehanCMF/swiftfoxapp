<?php

use App\Http\Controllers\Web\WhatsAppTestController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

Route::get('/whatsapp-test', [WhatsAppTestController::class, 'show']);
Route::post('/whatsapp-test/connect', [WhatsAppTestController::class, 'connect'])
    ->name('whatsapp-test.connect');

// Serve static files if they exist, otherwise serve SPA
Route::get('/{any?}', function ($any = null) {
    if ($any) {
        $path = public_path($any);
            if (File::exists($path)) {
                return response()->file($path);
            }
        }
        return view('app');
})->where('any', '.*');
