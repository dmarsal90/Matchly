<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Http\Requests;

use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\Region;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Profiles\Domain\Enums\Dealbreaker;
use App\Modules\Profiles\Domain\Enums\LanguageLevel;
use App\Modules\Profiles\Domain\Enums\QuestionnaireStep;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveQuestionnaireStepRequest extends FormRequest
{
    public const int MAX_SKILLS = 40;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return match ($this->step()) {
            QuestionnaireStep::Role => [
                'target_roles' => ['required', 'array', 'min:1', 'max:3'],
                'target_roles.*' => ['distinct', Rule::enum(JobRole::class)],
                'seniority' => ['required', Rule::enum(Seniority::class)],
                'years_experience' => ['required', 'integer', 'min:0', 'max:50'],
            ],
            QuestionnaireStep::Skills => [
                'skills' => ['present', 'array', 'max:'.self::MAX_SKILLS],
                'skills.*.name' => ['required', 'string', 'max:60'],
                'skills.*.level' => ['required', 'integer', 'min:1', 'max:5'],
                'skills.*.years' => ['nullable', 'numeric', 'min:0', 'max:50'],
                'skills.*.last_used_year' => ['nullable', 'integer', 'min:1980', 'max:'.CarbonImmutable::now()->year],
            ],
            QuestionnaireStep::Languages => [
                'languages' => ['present', 'array', 'max:15'],
                'languages.*.code' => ['required', 'string', 'regex:/^[a-z]{2,3}$/', 'distinct'],
                'languages.*.level' => ['required', Rule::enum(LanguageLevel::class)],
            ],
            QuestionnaireStep::Location => [
                'regions' => ['present', 'array'],
                'regions.*' => ['distinct', Rule::enum(Region::class)],
                'utc_offset_min' => ['nullable', 'integer', 'min:-12', 'max:14'],
                'utc_offset_max' => ['nullable', 'integer', 'min:-12', 'max:14', 'gte:utc_offset_min'],
                'work_modes' => ['present', 'array'],
                'work_modes.*' => ['distinct', Rule::enum(WorkMode::class)],
                'employment_types' => ['present', 'array'],
                'employment_types.*' => ['distinct', Rule::enum(EmploymentType::class)],
            ],
            QuestionnaireStep::Conditions => [
                'salary_min' => ['nullable', 'integer', 'min:0', 'max:10000000'],
                'salary_currency' => ['nullable', 'required_with:salary_min', Rule::in(UpdateProfileRequest::CURRENCIES)],
                'salary_period' => ['nullable', 'required_with:salary_min', Rule::enum(SalaryPeriod::class)],
                'dealbreakers' => ['present', 'array'],
                'dealbreakers.*' => ['distinct', Rule::enum(Dealbreaker::class)],
                'dealbreaker_notes' => ['nullable', 'string', 'max:500'],
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'skills.*.name' => __('onboarding.skills.name'),
            'skills.*.level' => __('onboarding.skills.level'),
        ];
    }

    public function step(): QuestionnaireStep
    {
        $step = $this->route('step');

        return $step instanceof QuestionnaireStep ? $step : QuestionnaireStep::from((string) $step);
    }

    /**
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return list<T>
     */
    public function enumList(string $key, string $enum): array
    {
        return array_values(array_map(fn (string $value) => $enum::from($value), (array) $this->validated($key, [])));
    }

    public function optionalInt(string $key): ?int
    {
        $value = $this->validated($key);

        return $value !== null && $value !== '' ? (int) $value : null;
    }

    /**
     * @return list<array{name: string, level: int, years: float|null, last_used_year: int|null}>
     */
    public function skills(): array
    {
        /** @var list<array{name: string, level: int|string, years?: float|string|null, last_used_year?: int|string|null}> $skills */
        $skills = $this->validated('skills', []);

        return array_map(fn (array $skill): array => [
            'name' => trim($skill['name']),
            'level' => (int) $skill['level'],
            'years' => isset($skill['years']) && $skill['years'] !== '' ? round((float) $skill['years'], 1) : null,
            'last_used_year' => isset($skill['last_used_year']) && $skill['last_used_year'] !== '' ? (int) $skill['last_used_year'] : null,
        ], $skills);
    }

    /**
     * @return array<string, LanguageLevel>
     */
    public function languages(): array
    {
        $languages = [];
        /** @var list<array{code: string, level: string}> $rows */
        $rows = $this->validated('languages', []);
        foreach ($rows as $row) {
            $languages[$row['code']] = LanguageLevel::from($row['level']);
        }

        return $languages;
    }
}
