<?php

use Illuminate\Support\Facades\Route;



use Illuminate\Support\Facades\File;

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
