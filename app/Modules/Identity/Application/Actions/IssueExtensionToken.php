<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Creates a browser extension token. The plain token is returned once; only its hash is kept. */
final readonly class IssueExtensionToken
{
    public const int MAX_PER_USER = 5;

    public const string PREFIX = 'mtx_';

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function handle(User $user, string $name): string
    {
        if ($user->extensionTokens()->count() >= self::MAX_PER_USER) {
            throw ValidationException::withMessages(['name' => __('extension.too_many', ['max' => self::MAX_PER_USER])]);
        }

        $token = self::PREFIX.Str::random(40);
        $user->extensionTokens()->create(['name' => $name, 'token_hash' => self::hash($token)]);

        return $token;
    }
}
