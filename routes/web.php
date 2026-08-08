<?php

declare(strict_types=1);

use App\Http\Controllers\BillingController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\ProtocolController;
use App\Http\Controllers\ProtocolEvidenceController;
use App\Http\Controllers\ProtocolItemController;
use App\Http\Controllers\ProtocolItemPhotoController;
use App\Http\Controllers\ProtocolRoomController;
use App\Http\Controllers\ProtocolSubmitController;
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

// D5: QR verify alias for PDF generation
Route::get('/qr/{hash}', [QrVerificationController::class, 'show'])
    ->name('qr.verify')
    ->where('hash', '[a-f0-9]{64}');

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

/*
|--------------------------------------------------------------------------
| M8 Phase 2: Cabinet (kabinet) placeholder
|--------------------------------------------------------------------------
| Landing spot after login/registration. Full property list + protocol
| history is Phase 3 scope — this is the minimal real page GATE 2 needs
| to make "zarejestrował się → wpadł do kabinetu" an actual, testable
| route rather than a dead end.
*/
Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    /*
    |----------------------------------------------------------------
    | M10 GATE 2, Scenario A slice: Properties (public, own-only)
    |----------------------------------------------------------------
    */
    Route::prefix('properties')->name('properties.')->group(function (): void {
        Route::get('/', [PropertyController::class, 'index'])->name('index');
        Route::get('/create', [PropertyController::class, 'create'])->name('create');
        Route::post('/', [PropertyController::class, 'store'])->name('store');
    });

    /*
    |----------------------------------------------------------------
    | M10.1, Scenario A slice: check-in protocol creation from an
    | existing property (draft only — see ProtocolController).
    |----------------------------------------------------------------
    */
    Route::prefix('protocols')->name('protocols.')->group(function (): void {
        Route::get('/create', [ProtocolController::class, 'create'])->name('create');
        Route::post('/', [ProtocolController::class, 'store'])->name('store');
        Route::get('/{protocol}', [ProtocolController::class, 'show'])->name('show');
    });

    /*
    |----------------------------------------------------------------
    | M10.2, Scenario A slice: rooms + items CRUD on a draft check-in
    | protocol (draft-only + initiator guard — see
    | AuthorizesDraftProtocolMutation).
    |----------------------------------------------------------------
    */
    Route::prefix('protocols/{protocol}')->name('protocols.')->group(function (): void {
        Route::post('/rooms', [ProtocolRoomController::class, 'store'])->name('rooms.store');
        Route::put('/rooms/{room}', [ProtocolRoomController::class, 'update'])->name('rooms.update');
        Route::delete('/rooms/{room}', [ProtocolRoomController::class, 'destroy'])->name('rooms.destroy');

        Route::post('/rooms/{room}/items', [ProtocolItemController::class, 'store'])->name('rooms.items.store');
        Route::put('/rooms/{room}/items/{item}', [ProtocolItemController::class, 'update'])->name('rooms.items.update');
        Route::delete('/rooms/{room}/items/{item}', [ProtocolItemController::class, 'destroy'])->name('rooms.items.destroy');
    });

    /*
    |----------------------------------------------------------------
    | M10.3, Scenario A slice: photo evidence for items on a draft
    | check-in protocol. Upload/delete go through EvidenceUploadService
    | only (SHA-256 hash + MinIO), draft-only + initiator guard. The
    | evidence.show route proxies the file from MinIO's internal
    | endpoint to the browser (see ProtocolEvidenceController).
    |----------------------------------------------------------------
    */
    Route::prefix('protocols/{protocol}')->name('protocols.')->group(function (): void {
        Route::get('/evidence/{evidence}', [ProtocolEvidenceController::class, 'show'])->name('evidence.show');

        Route::post('/rooms/{room}/items/{item}/photos', [ProtocolItemPhotoController::class, 'store'])
            ->name('rooms.items.photos.store');
        Route::delete('/rooms/{room}/items/{item}/photos/{evidence}', [ProtocolItemPhotoController::class, 'destroy'])
            ->name('rooms.items.photos.destroy');
    });

    /*
    |----------------------------------------------------------------
    | M10.4, Scenario A slice: submit a draft check-in protocol to the
    | counterparty. Hard-gated on a real, consumed entitlement (see
    | ProtocolSubmitController — no new gate, wires InvitationService's
    | existing one).
    |----------------------------------------------------------------
    */
    Route::prefix('protocols/{protocol}')->name('protocols.')->group(function (): void {
        Route::post('/submit', [ProtocolSubmitController::class, 'store'])->name('submit');
    });

    /*
    |----------------------------------------------------------------
    | M10 GATE 2: Billing / entitlements (dev-grant stands in for P24
    | until the real Przelewy24 slice lands — see BillingController).
    |----------------------------------------------------------------
    */
    Route::prefix('billing')->name('billing.')->group(function (): void {
        Route::get('/', [BillingController::class, 'index'])->name('index');
        Route::post('/dev-grant', [BillingController::class, 'devGrant'])->name('dev-grant');
    });
});

require __DIR__.'/auth.php';
