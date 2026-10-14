<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Domain\Enums\ImportStatus;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use App\Modules\Profiles\Policies\ProfileImportPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Data read from one source. It waits in "pending_review" until the user
 * confirms it; the confirmed import of each source is the copy we keep
 * (skills are also written to user_skills for matching).
 *
 * @property int $id
 * @property int $user_id
 * @property SkillSource $source
 * @property ImportStatus $status
 * @property string|null $reference
 * @property array<string, mixed>|null $payload
 * @property string|null $error
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UsePolicy(ProfileImportPolicy::class)]
final class ProfileImport extends Model
{
    protected $fillable = ['source', 'status', 'reference', 'payload', 'error', 'confirmed_at'];

    protected $hidden = ['payload'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeStatus(Builder $query, ImportStatus $status): void
    {
        $query->where('status', $status);
    }

    protected function casts(): array
    {
        return [
            'source' => SkillSource::class,
            'status' => ImportStatus::class,
            // Imported CVs, positions and README excerpts are personal data: encrypted at rest.
            'payload' => 'encrypted:array',
            'confirmed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
