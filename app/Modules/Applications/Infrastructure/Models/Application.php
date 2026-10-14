<?php

declare(strict_types=1);

namespace App\Modules\Applications\Infrastructure\Models;

use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Policies\ApplicationPolicy;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $job_listing_id
 * @property ApplicationStatus $status
 * @property string|null $notes
 * @property CarbonImmutable $status_changed_at
 * @property CarbonImmutable|null $applied_at
 * @property CarbonImmutable $created_at
 * @property-read JobListing $job
 */
#[UsePolicy(ApplicationPolicy::class)]
final class Application extends Model
{
    protected $fillable = ['status', 'notes', 'status_changed_at', 'applied_at'];

    protected $attributes = [
        'status' => 'saved',
        'notes' => null,
        'applied_at' => null,
    ];

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
            'status' => ApplicationStatus::class,
            'notes' => 'encrypted',
            'status_changed_at' => 'immutable_datetime',
            'applied_at' => 'immutable_datetime',
        ];
    }
}
