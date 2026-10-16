<?php

declare(strict_types=1);

namespace App\Modules\Alerts\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $company_key
 * @property string $company
 * @property CarbonImmutable $last_checked_at
 * @property CarbonImmutable|null $last_notified_at
 * @property CarbonImmutable $created_at
 * @property-read User|null $user
 */
final class FollowedCompany extends Model
{
    protected $fillable = ['company_key', 'company', 'last_checked_at'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['last_checked_at' => 'immutable_datetime', 'last_notified_at' => 'immutable_datetime'];
    }
}
