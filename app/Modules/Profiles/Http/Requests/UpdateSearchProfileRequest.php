<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Http\Requests;

use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Profiles\Domain\Data\SearchProfile;
use App\Modules\Profiles\Domain\Services\SkillCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSearchProfileRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'roles' => ['present', 'array', 'max:5'],
            'roles.*' => ['distinct', Rule::enum(JobRole::class)],
            'technologies' => ['present', 'array', 'max:15'],
            'technologies.*' => ['string', 'max:60'],
            'seniorities' => ['present', 'array'],
            'seniorities.*' => ['distinct', Rule::enum(Seniority::class)],
            'work_modes' => ['present', 'array'],
            'work_modes.*' => ['distinct', Rule::enum(WorkMode::class)],
            'employment_types' => ['present', 'array'],
            'employment_types.*' => ['distinct', Rule::enum(EmploymentType::class)],
            'salary_min' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'salary_currency' => ['nullable', 'required_with:salary_min', Rule::in(UpdateProfileRequest::CURRENCIES)],
        ];
    }

    public function toSearchProfile(SkillCatalog $catalog): SearchProfile
    {
        $technologies = [];
        foreach ((array) $this->validated('technologies', []) as $name) {
            $skill = $catalog->normalize((string) $name);
            if ($skill !== null && ! in_array($skill->slug, $technologies, true)) {
                $technologies[] = $skill->slug;
            }
        }

        $salary = $this->validated('salary_min');

        return SearchProfile::fromArray([
            ...$this->validated(),
            'technologies' => $technologies,
            'salary_min' => $salary !== null ? (int) $salary : null,
            'salary_currency' => $salary !== null ? $this->validated('salary_currency') : null,
        ]);
    }
}
