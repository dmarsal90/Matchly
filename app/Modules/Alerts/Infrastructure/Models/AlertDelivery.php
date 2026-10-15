<?php

declare(strict_types=1);

namespace App\Modules\Alerts\Infrastructure\Models;

use App\Modules\Jobs\Infrastructure\Models\JobListing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $saved_search_id
 * @property int $job_listing_id
 * @property CarbonImmutable $matched_at
 * @property CarbonImmutable|null $notified_at
 * @property-read JobListing|null $job
 */
final class AlertDelivery extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<SavedSearch, $this>
     */
    public function savedSearch(): BelongsTo
    {
        return $this->belongsTo(SavedSearch::class);
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
            'matched_at' => 'immutable_datetime',
            'notified_at' => 'immutable_datetime',
        ];
    }
}
