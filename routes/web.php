<?php

declare(strict_types=1);

use App\Modules\Document\Application\Controllers\QrVerificationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'laravelVersion' => app()->version(),
        'phpVersion' => PHP_VERSION,
    ]);
});

/*
|--------------------------------------------------------------------------
| D5: QR Verification Routes (Public)
|--------------------------------------------------------------------------
| Public pages for document verification via QR code.
| Does NOT expose PII (party names, private photos).
*/
Route::prefix('verify')->name('verify.')->group(function (): void {
    Route::get('/{hash}', [QrVerificationController::class, 'show'])
        ->name('document')
        ->where('hash', '[a-f0-9]{64}');

    Route::post('/api', [QrVerificationController::class, 'verify'])
        ->name('api');
});

/*
|--------------------------------------------------------------------------
| D1: Payment Routes
|--------------------------------------------------------------------------
| Routes for Przelewy24 payment flow.
*/
Route::prefix('payment')->name('payment.')->group(function (): void {
    Route::get('/return/{session}', function (string $session) {
        return Inertia::render('Payment/Return', ['session' => $session]);
    })->name('return');

    Route::post('/webhook', function () {
        // Webhook handler - implemented in controller
        return response()->json(['status' => 'ok']);
    })->name('webhook');

    // Sandbox simulation page
    Route::get('/sandbox/{session}', function (string $session) {
        return Inertia::render('Payment/Sandbox', ['session' => $session]);
    })->name('sandbox');
});

/*
|--------------------------------------------------------------------------
| Invitation Routes (Magic Link)
|--------------------------------------------------------------------------
*/
Route::get('/invitation/{token}', function (string $token) {
    return Inertia::render('Invitation/Accept', ['token' => $token]);
})->name('invitation.accept');
