<?php

declare(strict_types=1);

namespace App\Modules\Alerts\Infrastructure\Models;

use App\Modules\Alerts\Domain\Enums\AlertFrequency;
use App\Modules\Alerts\Policies\SavedSearchPolicy;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Application\Queries\JobFilters;
use App\Modules\Jobs\Application\Queries\JobFiltersParser;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property array<string, string> $filters
 * @property AlertFrequency $frequency
 * @property bool $email
 * @property bool $active
 * @property CarbonImmutable|null $last_notified_at
 * @property CarbonImmutable $created_at
 * @property-read User|null $user
 */
#[UsePolicy(SavedSearchPolicy::class)]
final class SavedSearch extends Model
{
    protected $fillable = ['name', 'filters', 'frequency', 'email', 'active'];

    protected $attributes = [
        'email' => true,
        'active' => true,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<AlertDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(AlertDelivery::class);
    }

    /** The listing filters of this alert, evaluated for its owner (score filter included). */
    public function jobFilters(): JobFilters
    {
        return JobFiltersParser::parse($this->filters, $this->user_id);
    }

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'frequency' => AlertFrequency::class,
            'email' => 'boolean',
            'active' => 'boolean',
            'last_notified_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
