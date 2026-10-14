<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\OAuthController;
use App\Modules\Profiles\Http\Controllers\CvDocumentController;
use App\Modules\Profiles\Http\Controllers\ProfileController;
use App\Modules\Shared\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::put('locale', [LocaleController::class, 'update'])
    ->middleware('throttle:30,1')
    ->name('locale.update');

Route::inertia('privacy', 'legal/Privacy', ['retention' => [
    'days' => (string) config('privacy.retention.unverified_account_days'),
    'months' => (string) config('privacy.retention.inactive_cv_text_months'),
]])->name('legal.privacy');
Route::inertia('terms', 'legal/Terms')->name('legal.terms');

Route::middleware('throttle:oauth')->group(function () {
    Route::get('auth/{provider}/redirect', [OAuthController::class, 'redirect'])->name('oauth.redirect');
    Route::get('auth/{provider}/callback', [OAuthController::class, 'callback'])->name('oauth.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // Professional profile ("career"); account settings keep the starter kit's profile.* names.
    Route::get('profile', [ProfileController::class, 'edit'])->name('career.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('career.update');

    Route::post('profile/cv', [CvDocumentController::class, 'store'])->middleware('throttle:uploads')->name('career.cv.store');
    Route::get('profile/cv/{cv}', [CvDocumentController::class, 'download'])->name('career.cv.download');
    Route::patch('profile/cv/{cv}/primary', [CvDocumentController::class, 'primary'])->name('career.cv.primary');
    Route::delete('profile/cv/{cv}', [CvDocumentController::class, 'destroy'])->name('career.cv.destroy');
});

require __DIR__.'/settings.php';
