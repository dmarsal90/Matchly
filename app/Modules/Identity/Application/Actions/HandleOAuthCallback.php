<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Enums\OAuthProvider;
use App\Modules\Identity\Infrastructure\Models\SocialAccount;
use App\Modules\Identity\Infrastructure\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use RuntimeException;

/**
 * Finds or creates the local account for an OAuth identity.
 *
 * Account pre-hijacking guard: an unverified local account with the same
 * e-mail may have been registered by someone else, so linking to it wipes
 * its password and existing sessions before the OAuth owner takes it over.
 */
final readonly class HandleOAuthCallback
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(OAuthProvider $provider, SocialiteUser $identity, ?User $current = null): User
    {
        $account = SocialAccount::query()
            ->with('user')
            ->where('provider', $provider)
            ->where('provider_user_id', (string) $identity->getId())
            ->first();

        if ($account !== null) {
            $this->storeTokens($account, $identity);

            return $account->user ?? throw new RuntimeException("Social account {$account->id} has no user.");
        }

        $email = $identity->getEmail();
        if ($current === null && ($email === null || $email === '')) {
            throw ValidationException::withMessages(['email' => __('oauth.email_required')]);
        }

        return $this->db->transaction(function () use ($provider, $identity, $current, $email): User {
            $user = $current ?? User::query()->where('email', $email)->first();

            if ($user === null) {
                $user = new User;
                $user->forceFill([
                    'name' => $identity->getName() ?: ($identity->getNickname() ?: (string) $email),
                    'email' => $email,
                    'password' => null,
                    // The provider verified the address.
                    'email_verified_at' => CarbonImmutable::now(),
                ])->save();
            } elseif ($current === null && ! $user->hasVerifiedEmail()) {
                $user->forceFill(['password' => null, 'email_verified_at' => CarbonImmutable::now(), 'remember_token' => null])->save();
                $this->db->table('sessions')->where('user_id', $user->id)->delete();
            }

            $account = new SocialAccount(['provider' => $provider, 'provider_user_id' => (string) $identity->getId()]);
            $account->user()->associate($user);
            $this->storeTokens($account, $identity);

            return $user;
        });
    }

    private function storeTokens(SocialAccount $account, SocialiteUser $identity): void
    {
        $account->fill([
            'nickname' => $identity->getNickname(),
            'token' => property_exists($identity, 'token') ? $identity->token : null,
            'refresh_token' => property_exists($identity, 'refreshToken') ? $identity->refreshToken : null,
            'token_expires_at' => property_exists($identity, 'expiresIn') && is_numeric($identity->expiresIn)
                ? CarbonImmutable::now()->addSeconds((int) $identity->expiresIn)
                : null,
        ])->save();
    }
}
