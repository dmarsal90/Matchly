<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Models;

use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property ConsentPurpose $purpose
 * @property string $policy_version
 * @property CarbonImmutable $granted_at
 * @property CarbonImmutable|null $revoked_at
 */
final class Consent extends Model
{
    protected $fillable = ['purpose', 'policy_version', 'granted_at', 'revoked_at'];

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
            'purpose' => ConsentPurpose::class,
            'granted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
