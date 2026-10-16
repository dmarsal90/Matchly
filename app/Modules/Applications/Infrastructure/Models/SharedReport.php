<?php

declare(strict_types=1);

namespace App\Modules\Applications\Infrastructure\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property string $html
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $last_viewed_at
 * @property int $views
 * @property CarbonImmutable $created_at
 */
final class SharedReport extends Model
{
    protected $fillable = ['token_hash', 'html', 'expires_at'];

    protected $hidden = ['token_hash', 'html'];

    protected function casts(): array
    {
        return [
            'html' => 'encrypted',
            'expires_at' => 'immutable_datetime',
            'last_viewed_at' => 'immutable_datetime',
        ];
    }
}
