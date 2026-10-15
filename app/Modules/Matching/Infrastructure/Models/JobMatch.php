<?php

declare(strict_types=1);

namespace App\Modules\Matching\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $job_listing_id
 * @property int $score
 * @property string $level
 * @property bool $eligible
 * @property string|null $ineligible_reason
 * @property array<string, mixed> $result
 * @property CarbonImmutable $computed_at
 */
final class JobMatch extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<JobListing, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(JobListing::class, 'job_listing_id');
    }

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'eligible' => 'boolean',
            'result' => 'array',
            'computed_at' => 'immutable_datetime',
        ];
    }
}
