<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Middleware\TrackLastActivity;
use App\Modules\Shared\Http\Middleware\HandleAppearance;
use App\Modules\Shared\Http\Middleware\HandleInertiaRequests;
use App\Modules\Shared\Http\Middleware\SecurityHeaders;
use App\Modules\Shared\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            Route::prefix('api/extension')->middleware('api')->group(base_path('routes/extension.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->append(SecurityHeaders::class);

        // Mail clients POST one-click unsubscribes without a session: the signed URL protects them.
        $middleware->preventRequestForgery(except: ['alerts/*/unsubscribe']);

        $middleware->web(append: [
            SetLocale::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            TrackLastActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
