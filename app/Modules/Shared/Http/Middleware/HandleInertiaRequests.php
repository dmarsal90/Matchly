<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Middleware;

use App\Modules\Identity\Http\Controllers\OAuthController;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Shared\Infrastructure\Localization\SupportedLocales;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    /**
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(private readonly SupportedLocales $locales) {}

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => app()->getLocale(),
            'supportedLocales' => $this->locales->all(),
            'auth' => [
                'user' => $this->user($request),
            ],
            'oauthProviders' => OAuthController::enabledProviders(),
            // Unread count for the header bell; live updates come over the broadcast channel.
            'notifications' => fn (): ?array => $request->user() instanceof User ? ['unread' => $request->user()->unreadNotifications()->count()] : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Only what the UI needs on every page; the full profile is loaded where it is used.
     *
     * @return array<string, mixed>|null
     */
    private function user(Request $request): ?array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'has_password' => $user->password !== null,
            'is_admin' => $user->is_admin,
            'locale' => $user->locale,
            'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
            'avatar' => null,
        ];
    }
}
