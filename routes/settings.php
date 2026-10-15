<?php

declare(strict_types=1);

use App\Modules\AI\Http\Controllers\AiSettingsController;
use App\Modules\Identity\Http\Controllers\PrivacyController;
use App\Modules\Identity\Http\Controllers\Settings\ProfileController;
use App\Modules\Identity\Http\Controllers\Settings\SecurityController;
use App\Modules\Identity\Http\Middleware\RequirePasswordIfSet;
use App\Modules\Notifications\Http\Controllers\NotificationPreferencesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('settings', fn () => to_route('profile.edit'))->name('settings');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePasswordIfSet::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    Route::get('settings/privacy', [PrivacyController::class, 'edit'])->name('privacy.edit');
    Route::put('settings/privacy/consent', [PrivacyController::class, 'updateConsent'])->name('privacy.consent');
    Route::get('settings/notifications', [NotificationPreferencesController::class, 'edit'])->name('notification-preferences.edit');
    Route::put('settings/notifications', [NotificationPreferencesController::class, 'update'])->name('notification-preferences.update');

    Route::get('settings/ai', [AiSettingsController::class, 'edit'])->name('ai.edit');
    Route::put('settings/ai/key', [AiSettingsController::class, 'update'])->middleware('throttle:10,1')->name('ai.key.update');
    Route::delete('settings/ai/key', [AiSettingsController::class, 'destroy'])->name('ai.key.destroy');

    Route::get('settings/privacy/export', [PrivacyController::class, 'export'])->middleware('throttle:exports')->name('privacy.export');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
