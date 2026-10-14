<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Enums;

enum OAuthProvider: string
{
    case Google = 'google';
    case Github = 'github';

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        // Identity only: GitHub data import uses the public API, not this token.
        return match ($this) {
            self::Google => ['openid', 'email', 'profile'],
            self::Github => ['read:user', 'user:email'],
        };
    }
}
