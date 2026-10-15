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
        // Identity only. The GitHub import may send this token, but only to public endpoints (higher rate limit).
        return match ($this) {
            self::Google => ['openid', 'email', 'profile'],
            self::Github => ['read:user', 'user:email'],
        };
    }
}
