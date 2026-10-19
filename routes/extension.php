<?php

declare(strict_types=1);

use App\Modules\Applications\Http\Controllers\ExtensionApiController;
use App\Modules\Identity\Http\Middleware\AuthenticateExtensionToken;
use Illuminate\Support\Facades\Route;

/*
 * The browser extension's API (read-only plus draft answers, nothing stored): stateless, by bearer token, and
 * limited to the packages the person has approved. Loaded under /api/extension.
 */
Route::middleware([AuthenticateExtensionToken::class, 'throttle:60,1'])->group(function (): void {
    Route::get('packages', [ExtensionApiController::class, 'index'])->name('extension.api.packages');
    Route::get('packages/{package}/fields', [ExtensionApiController::class, 'fields'])->whereNumber('package')->name('extension.api.fields');
    Route::post('jobs', [ExtensionApiController::class, 'capture'])->middleware('throttle:30,60')->name('extension.api.capture');
    Route::post('packages/{package}/answer', [ExtensionApiController::class, 'answer'])->whereNumber('package')->middleware('throttle:20,1')->name('extension.api.answer');
    Route::get('packages/{package}/resume.pdf', [ExtensionApiController::class, 'resume'])->whereNumber('package')->name('extension.api.resume');
});
