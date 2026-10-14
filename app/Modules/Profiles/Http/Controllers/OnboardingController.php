<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Http\Controllers;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\Region;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Profiles\Application\Actions\SaveQuestionnaireStep;
use App\Modules\Profiles\Domain\Enums\Dealbreaker;
use App\Modules\Profiles\Domain\Enums\LanguageLevel;
use App\Modules\Profiles\Domain\Enums\QuestionnaireStep;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use App\Modules\Profiles\Domain\Services\SkillCatalog;
use App\Modules\Profiles\Http\Requests\SaveQuestionnaireStepRequest;
use App\Modules\Profiles\Http\Requests\UpdateProfileRequest;
use App\Modules\Profiles\Infrastructure\Models\UserSkill;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The step-by-step skills questionnaire (onboarding), editable at any time. */
final class OnboardingController
{
    public function show(Request $request, #[CurrentUser] User $user, SkillCatalog $catalog): Response
    {
        $step = QuestionnaireStep::tryFrom((string) $request->query('step')) ?? QuestionnaireStep::Role;
        $profile = $user->profile()->firstOrNew();

        return Inertia::render('onboarding/Questionnaire', [
            'step' => $step->value,
            'steps' => array_column(QuestionnaireStep::cases(), 'value'),
            'completed' => $profile->onboarding_completed_at !== null,
            'answers' => [
                'target_roles' => $profile->target_roles,
                'seniority' => $profile->seniority?->value,
                'years_experience' => $profile->years_experience,
                'skills' => $user->skills()->where('source', SkillSource::Declared)->orderBy('id')->get()
                    ->map(fn (UserSkill $skill): array => [
                        'name' => $skill->name,
                        'level' => $skill->level ?? 3,
                        'years' => $skill->years,
                        'last_used_year' => $skill->last_used_year,
                    ])->values()->all(),
                'languages' => $profile->languages ?? [],
                'regions' => $profile->regions,
                'utc_offset_min' => $profile->utc_offset_min,
                'utc_offset_max' => $profile->utc_offset_max,
                'work_modes' => array_map(fn (WorkMode $mode): string => $mode->value, $profile->workModes()),
                'employment_types' => array_map(fn (EmploymentType $type): string => $type->value, $profile->employmentTypes()),
                'salary_min' => $profile->salary_min,
                'salary_currency' => $profile->salary_currency,
                'salary_period' => $profile->salary_period?->value,
                'dealbreakers' => $profile->dealbreakers,
                'dealbreaker_notes' => $profile->dealbreaker_notes,
            ],
            'options' => Inertia::once(fn (): array => [
                'roles' => array_column(JobRole::cases(), 'value'),
                'seniorities' => array_column(Seniority::cases(), 'value'),
                'skillSuggestions' => $catalog->labels(),
                'maxSkills' => SaveQuestionnaireStepRequest::MAX_SKILLS,
                'currentYear' => CarbonImmutable::now()->year,
                'languageLevels' => array_column(LanguageLevel::cases(), 'value'),
                'regions' => array_column(Region::cases(), 'value'),
                'workModes' => array_column(WorkMode::cases(), 'value'),
                'employmentTypes' => array_column(EmploymentType::cases(), 'value'),
                'currencies' => UpdateProfileRequest::CURRENCIES,
                'salaryPeriods' => array_column(SalaryPeriod::cases(), 'value'),
                'dealbreakers' => array_column(Dealbreaker::cases(), 'value'),
            ]),
        ]);
    }

    public function update(SaveQuestionnaireStepRequest $request, #[CurrentUser] User $user, SaveQuestionnaireStep $save): RedirectResponse
    {
        $step = $request->step();
        $firstCompletion = $user->profile()->whereNotNull('onboarding_completed_at')->doesntExist();

        match ($step) {
            QuestionnaireStep::Role => $save->role(
                $user,
                $request->enumList('target_roles', JobRole::class),
                Seniority::from((string) $request->validated('seniority')),
                (int) $request->validated('years_experience'),
            ),
            QuestionnaireStep::Skills => $save->skills($user, $request->skills()),
            QuestionnaireStep::Languages => $save->languages($user, $request->languages()),
            QuestionnaireStep::Location => $save->location(
                $user,
                $request->enumList('regions', Region::class),
                $request->optionalInt('utc_offset_min'),
                $request->optionalInt('utc_offset_max'),
                $request->enumList('work_modes', WorkMode::class),
                $request->enumList('employment_types', EmploymentType::class),
            ),
            QuestionnaireStep::Conditions => $save->conditions(
                $user,
                $request->optionalInt('salary_min'),
                is_string($request->validated('salary_currency')) ? $request->validated('salary_currency') : null,
                SalaryPeriod::tryFrom((string) $request->validated('salary_period')),
                $request->enumList('dealbreakers', Dealbreaker::class),
                is_string($request->validated('dealbreaker_notes')) ? $request->validated('dealbreaker_notes') : null,
            ),
        };

        $next = $step->next();
        if ($next !== null) {
            return to_route('onboarding.show', ['step' => $next->value]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __($firstCompletion ? 'onboarding.completed' : 'onboarding.saved')]);

        return to_route('skills.index');
    }
}
