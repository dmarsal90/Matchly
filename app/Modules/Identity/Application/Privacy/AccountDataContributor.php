<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Privacy;

use App\Modules\Identity\Domain\Contracts\PersonalDataContributor;
use App\Modules\Identity\Domain\Data\PersonalData;
use App\Modules\Identity\Infrastructure\Models\Consent;
use App\Modules\Identity\Infrastructure\Models\SocialAccount;
use App\Modules\Identity\Infrastructure\Models\User;

final class AccountDataContributor implements PersonalDataContributor
{
    public function export(int $userId): PersonalData
    {
        $user = User::query()->with(['consents', 'socialAccounts'])->findOrFail($userId);

        return new PersonalData('account', [
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'locale' => $user->locale,
            'timezone' => $user->timezone,
            'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
            'created_at' => $user->created_at?->toIso8601String(),
            'consents' => $user->consents->map(fn (Consent $consent): array => [
                'purpose' => $consent->purpose->value,
                'policy_version' => $consent->policy_version,
                'granted_at' => $consent->granted_at->toIso8601String(),
                'revoked_at' => $consent->revoked_at?->toIso8601String(),
            ])->values()->all(),
            // Tokens are credentials, never exported.
            'linked_accounts' => $user->socialAccounts->map(fn (SocialAccount $account): array => [
                'provider' => $account->provider->value,
                'nickname' => $account->nickname,
            ])->values()->all(),
        ]);
    }

    public function erase(int $userId): void
    {
        // Everything here lives in tables that cascade from users.
    }
}
