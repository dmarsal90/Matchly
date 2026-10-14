<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\Region;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Profiles\Domain\Data\SearchProfile;
use App\Modules\Profiles\Domain\Enums\Dealbreaker;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $headline
 * @property string|null $phone
 * @property string|null $country
 * @property string|null $city
 * @property string|null $github_username
 * @property string|null $linkedin_url
 * @property string|null $portfolio_url
 * @property list<array{code: string, level: string}> $languages
 * @property int|null $salary_min
 * @property string|null $salary_currency
 * @property SalaryPeriod|null $salary_period
 * @property array<string, mixed> $preferences
 * @property list<string> $target_roles
 * @property Seniority|null $seniority
 * @property int|null $years_experience
 * @property list<string> $regions
 * @property int|null $utc_offset_min
 * @property int|null $utc_offset_max
 * @property list<string> $dealbreakers
 * @property string|null $dealbreaker_notes
 * @property array<string, mixed>|null $search_profile
 * @property CarbonImmutable|null $onboarding_completed_at
 */
final class Profile extends Model
{
    protected $fillable = [
        'headline', 'phone', 'country', 'city', 'github_username', 'linkedin_url', 'portfolio_url',
        'languages', 'salary_min', 'salary_currency', 'salary_period', 'preferences',
        'target_roles', 'seniority', 'years_experience', 'regions', 'utc_offset_min', 'utc_offset_max',
        'dealbreakers', 'dealbreaker_notes', 'search_profile',
    ];

    protected $attributes = [
        'languages' => '[]',
        'preferences' => '{}',
        'target_roles' => '[]',
        'regions' => '[]',
        'dealbreakers' => '[]',
    ];

    /**
     * @return list<JobRole>
     */
    public function targetRoles(): array
    {
        return array_values(array_filter(array_map(JobRole::tryFrom(...), $this->target_roles)));
    }

    /**
     * @return list<Region>
     */
    public function workableRegions(): array
    {
        return array_values(array_filter(array_map(Region::tryFrom(...), $this->regions)));
    }

    /**
     * @return list<Dealbreaker>
     */
    public function dealbreakerList(): array
    {
        return array_values(array_filter(array_map(Dealbreaker::tryFrom(...), $this->dealbreakers)));
    }

    /**
     * @return list<WorkMode>
     */
    public function workModes(): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $value): ?WorkMode => is_string($value) ? WorkMode::tryFrom($value) : null,
            (array) ($this->preferences['work_modes'] ?? []),
        )));
    }

    /**
     * @return list<EmploymentType>
     */
    public function employmentTypes(): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $value): ?EmploymentType => is_string($value) ? EmploymentType::tryFrom($value) : null,
            (array) ($this->preferences['employment_types'] ?? []),
        )));
    }

    public function searchProfile(): ?SearchProfile
    {
        return $this->search_profile !== null ? SearchProfile::fromArray($this->search_profile) : null;
    }

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
            'phone' => 'encrypted',
            'languages' => 'array',
            'preferences' => 'array',
            'salary_min' => 'integer',
            'salary_period' => SalaryPeriod::class,
            'target_roles' => 'array',
            'seniority' => Seniority::class,
            'years_experience' => 'integer',
            'regions' => 'array',
            'utc_offset_min' => 'integer',
            'utc_offset_max' => 'integer',
            'dealbreakers' => 'array',
            'search_profile' => 'array',
            'onboarding_completed_at' => 'immutable_datetime',
        ];
    }
}
