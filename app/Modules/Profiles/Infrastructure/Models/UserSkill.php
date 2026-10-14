<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Domain\Data\SkillClaim;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property SkillSource $source
 * @property string $skill
 * @property string $name
 * @property int|null $level
 * @property float|null $years
 * @property int|null $last_used_year
 * @property array<string, mixed>|null $evidence
 */
final class UserSkill extends Model
{
    protected $fillable = ['source', 'skill', 'name', 'level', 'years', 'last_used_year', 'evidence'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toClaim(): SkillClaim
    {
        return new SkillClaim(
            skill: $this->skill,
            name: $this->name,
            source: $this->source,
            level: $this->level,
            years: $this->years,
            lastUsedYear: $this->last_used_year,
            evidence: $this->evidence ?? [],
        );
    }

    protected function casts(): array
    {
        return [
            'source' => SkillSource::class,
            'level' => 'integer',
            'years' => 'float',
            'last_used_year' => 'integer',
            'evidence' => 'array',
        ];
    }
}
