<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Infrastructure\Models;

use App\Modules\Jobs\Domain\Enums\CloseReason;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\JobCategory;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\JobStatus;
use App\Modules\Jobs\Domain\Enums\RemoteScope;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Providers\Infrastructure\Models\Source;
use Carbon\CarbonImmutable;
use Database\Factories\JobListingFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $source_id
 * @property string $external_id
 * @property int|null $duplicate_of_id
 * @property string $title
 * @property string $company
 * @property string|null $company_logo_url
 * @property string $description
 * @property string $apply_url
 * @property string|null $source_url
 * @property string $canonical_url
 * @property string $fingerprint
 * @property string $content_hash
 * @property JobCategory|null $category
 * @property JobRole|null $role
 * @property WorkMode $work_mode
 * @property RemoteScope|null $remote_scope
 * @property list<string> $allowed_regions
 * @property string|null $location
 * @property EmploymentType|null $employment_type
 * @property Seniority|null $seniority
 * @property string|null $salary_min
 * @property string|null $salary_max
 * @property string|null $salary_currency
 * @property SalaryPeriod|null $salary_period
 * @property string|null $salary_annual_min
 * @property string|null $salary_annual_max
 * @property string|null $salary_text
 * @property list<string> $tags
 * @property array<string, mixed>|null $requirements
 * @property CarbonImmutable|null $requirements_extracted_at
 * @property CarbonImmutable|null $posted_at
 * @property CarbonImmutable $first_seen_at
 * @property CarbonImmutable $last_seen_at
 * @property CarbonImmutable $sort_at
 * @property JobStatus $status
 * @property CloseReason|null $close_reason
 * @property CarbonImmutable|null $closed_at
 * @property list<string>|null $flag_reasons
 * @property CarbonImmutable|null $link_checked_at
 * @property-read Source $source
 */
#[UseFactory(JobListingFactory::class)]
final class JobListing extends Model
{
    /** @use HasFactory<JobListingFactory> */
    use HasFactory;

    protected $guarded = ['id', 'sort_at', 'search_vector'];

    protected $attributes = [
        'status' => 'open',
        'allowed_regions' => '[]',
        'tags' => '[]',
    ];

    /**
     * @return BelongsTo<Source, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * @return BelongsTo<JobListing, $this>
     */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    public function slug(): string
    {
        return Str::slug(Str::limit($this->title.' '.$this->company, 80, ''));
    }

    /**
     * What users may see: open, not a duplicate and within the freshness window.
     *
     * @param  Builder<JobListing>  $query
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('status', JobStatus::Open)
            ->whereNull('duplicate_of_id')
            ->where('sort_at', '>=', CarbonImmutable::now()->subDays((int) config('jobs.max_age_days')));
    }

    /**
     * Newest first, with the id as a stable tie-breaker for cursor pagination.
     *
     * @param  Builder<JobListing>  $query
     */
    #[Scope]
    protected function latestFirst(Builder $query): void
    {
        $query->orderByDesc('sort_at')->orderByDesc('id');
    }

    protected function casts(): array
    {
        return [
            'category' => JobCategory::class,
            'role' => JobRole::class,
            'work_mode' => WorkMode::class,
            'remote_scope' => RemoteScope::class,
            'employment_type' => EmploymentType::class,
            'seniority' => Seniority::class,
            'salary_period' => SalaryPeriod::class,
            'status' => JobStatus::class,
            'close_reason' => CloseReason::class,
            'allowed_regions' => 'array',
            'tags' => 'array',
            'requirements' => 'array',
            'flag_reasons' => 'array',
            'salary_min' => 'decimal:2',
            'salary_max' => 'decimal:2',
            'salary_annual_min' => 'decimal:2',
            'salary_annual_max' => 'decimal:2',
            'requirements_extracted_at' => 'immutable_datetime',
            'posted_at' => 'immutable_datetime',
            'first_seen_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'sort_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'link_checked_at' => 'immutable_datetime',
        ];
    }
}
