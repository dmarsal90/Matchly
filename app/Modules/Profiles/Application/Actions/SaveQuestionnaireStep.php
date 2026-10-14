<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Actions;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\Region;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Profiles\Application\Events\ProfileChanged;
use App\Modules\Profiles\Domain\Enums\Dealbreaker;
use App\Modules\Profiles\Domain\Enums\LanguageLevel;
use App\Modules\Profiles\Domain\Enums\QuestionnaireStep;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use App\Modules\Profiles\Domain\Services\SkillCatalog;
use App\Modules\Profiles\Infrastructure\Models\Profile;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

/**
 * Saves one step of the onboarding questionnaire. Steps can be saved in any
 * order and revisited later; finishing the last one marks onboarding done.
 */
final readonly class SaveQuestionnaireStep
{
    public function __construct(
        private SkillCatalog $catalog,
        private ConnectionInterface $db,
        private Dispatcher $events,
    ) {}

    /**
     * @param  list<JobRole>  $roles
     */
    public function role(User $user, array $roles, Seniority $seniority, int $yearsExperience): Profile
    {
        return $this->save($user, QuestionnaireStep::Role, [
            'target_roles' => array_map(fn (JobRole $role): string => $role->value, $roles),
            'seniority' => $seniority,
            'years_experience' => $yearsExperience,
        ]);
    }

    /**
     * Replaces the declared skills. Synonyms collapse into one skill (the
     * last occurrence wins).
     *
     * @param  list<array{name: string, level: int, years: float|null, last_used_year: int|null}>  $skills
     */
    public function skills(User $user, array $skills): Profile
    {
        $rows = [];
        foreach ($skills as $skill) {
            $normalized = $this->catalog->normalize($skill['name']);
            if ($normalized === null) {
                continue;
            }

            $rows[$normalized->slug] = [
                'source' => SkillSource::Declared,
                'skill' => $normalized->slug,
                'name' => $normalized->label,
                'level' => $skill['level'],
                'years' => $skill['years'],
                'last_used_year' => $skill['last_used_year'],
            ];
        }

        return $this->db->transaction(function () use ($user, $rows): Profile {
            $user->skills()->where('source', SkillSource::Declared)->delete();
            foreach ($rows as $row) {
                $user->skills()->create($row);
            }

            return $this->save($user, QuestionnaireStep::Skills, [], forceChanged: true);
        });
    }

    /**
     * @param  array<string, LanguageLevel>  $languages  ISO code => level
     */
    public function languages(User $user, array $languages): Profile
    {
        return $this->save($user, QuestionnaireStep::Languages, [
            'languages' => array_map(
                fn (string $code, LanguageLevel $level): array => ['code' => strtolower($code), 'level' => $level->value],
                array_keys($languages),
                array_values($languages),
            ),
        ]);
    }

    /**
     * @param  list<Region>  $regions
     * @param  list<WorkMode>  $workModes
     * @param  list<EmploymentType>  $employmentTypes
     */
    public function location(User $user, array $regions, ?int $utcOffsetMin, ?int $utcOffsetMax, array $workModes, array $employmentTypes): Profile
    {
        $profile = $user->profile()->firstOrNew();

        return $this->save($user, QuestionnaireStep::Location, [
            'regions' => array_map(fn (Region $region): string => $region->value, $regions),
            'utc_offset_min' => $utcOffsetMin,
            'utc_offset_max' => $utcOffsetMax,
            'preferences' => [
                ...$profile->preferences ?? [],
                'work_modes' => array_map(fn (WorkMode $mode): string => $mode->value, $workModes),
                'employment_types' => array_map(fn (EmploymentType $type): string => $type->value, $employmentTypes),
            ],
        ]);
    }

    /**
     * @param  list<Dealbreaker>  $dealbreakers
     */
    public function conditions(User $user, ?int $salaryMin, ?string $salaryCurrency, ?SalaryPeriod $salaryPeriod, array $dealbreakers, ?string $notes): Profile
    {
        return $this->save($user, QuestionnaireStep::Conditions, [
            'salary_min' => $salaryMin,
            'salary_currency' => $salaryMin !== null && $salaryCurrency !== null ? strtoupper($salaryCurrency) : null,
            'salary_period' => $salaryMin !== null ? $salaryPeriod : null,
            'dealbreakers' => array_map(fn (Dealbreaker $dealbreaker): string => $dealbreaker->value, $dealbreakers),
            'dealbreaker_notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function save(User $user, QuestionnaireStep $step, array $attributes, bool $forceChanged = false): Profile
    {
        $profile = $user->profile()->firstOrNew();
        $profile->fill($attributes);

        if ($step->isLast() && $profile->onboarding_completed_at === null) {
            $profile->forceFill(['onboarding_completed_at' => CarbonImmutable::now()]);
        }

        $changed = $forceChanged || $profile->isDirty();
        $profile->save();

        if ($changed) {
            $this->events->dispatch(new ProfileChanged($user->id));
        }

        return $profile;
    }
}
