<?php

use AhmedAliraqi\LaravelDeepLink\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

// Domain verification files fetched by iOS and Android.
Route::get('.well-known/apple-app-site-association', [VerificationController::class, 'apple'])
    ->name('deep-link.apple');

Route::get('.well-known/assetlinks.json', [VerificationController::class, 'android'])
    ->name('deep-link.android');

// Older iOS versions check the file at the domain root.
if (config('deep-link.verification.legacy_apple_route', true)) {
    Route::get('apple-app-site-association', [VerificationController::class, 'apple'])
        ->name('deep-link.apple.legacy');
}
