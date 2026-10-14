<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $source_id
 * @property string $status running|succeeded|partial|failed
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $finished_at
 * @property int $fetched
 * @property int $created
 * @property int $updated
 * @property int $unchanged
 * @property int $skipped
 * @property int $duplicates
 * @property int $flagged
 * @property int $closed
 * @property list<string>|null $failures
 * @property array<string, int>|null $skip_reasons
 * @property string|null $error
 */
final class IngestionRun extends Model
{
    public const string RUNNING = 'running';

    public const string SUCCEEDED = 'succeeded';

    public const string PARTIAL = 'partial';

    public const string FAILED = 'failed';

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Source, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'failures' => 'array',
            'skip_reasons' => 'array',
        ];
    }
}
