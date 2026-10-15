<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Models;

use App\Modules\Identity\Domain\Enums\OAuthProvider;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property OAuthProvider $provider
 * @property string $provider_user_id
 * @property string|null $nickname
 * @property string|null $token
 * @property string|null $refresh_token
 * @property CarbonImmutable|null $token_expires_at
 */
final class SocialAccount extends Model
{
    protected $fillable = ['provider', 'provider_user_id', 'nickname', 'token', 'refresh_token', 'token_expires_at'];

    protected $hidden = ['token', 'refresh_token'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'provider' => OAuthProvider::class,
            'token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'immutable_datetime',
        ];
    }
}
