<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Actions\HandleOAuthCallback;
use App\Modules\Identity\Domain\Enums\OAuthProvider;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

final class OAuthController
{
    public function redirect(OAuthProvider $provider): SymfonyRedirect
    {
        abort_unless(self::configured($provider), 404);

        $driver = Socialite::driver($provider->value);

        if ($driver instanceof AbstractProvider) {
            $driver->scopes($provider->scopes());
        }

        return $driver->redirect();
    }

    public function callback(Request $request, OAuthProvider $provider, HandleOAuthCallback $handle): RedirectResponse
    {
        abort_unless(self::configured($provider), 404);

        try {
            $identity = Socialite::driver($provider->value)->user();
        } catch (Throwable) {
            // Denied consent, expired state or a provider error: start over.
            return to_route('login')->withErrors(['email' => __('oauth.failed')]);
        }

        /** @var User|null $current */
        $current = $request->user();

        try {
            $user = $handle->handle($provider, $identity, $current);
        } catch (ValidationException $exception) {
            return to_route('login')->withErrors($exception->errors());
        }

        if ($current !== null) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('oauth.linked')]);

            return to_route('security.edit');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('jobs.index'));
    }

    /**
     * @return list<string>
     */
    public static function enabledProviders(): array
    {
        return array_values(array_map(
            fn (OAuthProvider $provider): string => $provider->value,
            array_filter(OAuthProvider::cases(), self::configured(...)),
        ));
    }

    private static function configured(OAuthProvider $provider): bool
    {
        return filled(config("services.{$provider->value}.client_id")) && filled(config("services.{$provider->value}.client_secret"));
    }
}
