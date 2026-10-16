<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $token_hash
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable $created_at
 */
final class ExtensionToken extends Model
{
    protected $fillable = ['name', 'token_hash'];

    protected $hidden = ['token_hash'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['last_used_at' => 'immutable_datetime'];
    }
}
