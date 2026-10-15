<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Http\Requests;

use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Profiles\Domain\Data\ProfileData;
use App\Modules\Profiles\Domain\Enums\LanguageLevel;
use App\Modules\Shared\Infrastructure\Localization\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProfileRequest extends FormRequest
{
    public const array CURRENCIES = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'CHF', 'BRL', 'MXN', 'ARS', 'COP', 'CLP', 'INR', 'PLN', 'SEK', 'NOK', 'DKK', 'JPY'];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'headline' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ().-]{6,30}$/'],
            'country' => ['nullable', 'string', Rule::in(Countries::CODES)],
            'city' => ['nullable', 'string', 'max:80'],
            'github_username' => ['nullable', 'string', 'regex:/^[A-Za-z0-9](?:[A-Za-z0-9]|-(?=[A-Za-z0-9])){0,38}$/'],
            'linkedin_url' => ['nullable', 'url:https', 'max:255', 'regex:#^https://([a-z]{2,3}\.)?linkedin\.com/#i'],
            'portfolio_url' => ['nullable', 'url:https', 'max:255'],
            'languages' => ['array', 'max:15'],
            'languages.*.code' => ['required', 'string', 'regex:/^[a-z]{2,3}$/', 'distinct'],
            'languages.*.level' => ['required', Rule::enum(LanguageLevel::class)],
            'salary_min' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'salary_currency' => ['nullable', 'required_with:salary_min', Rule::in(self::CURRENCIES)],
            'salary_period' => ['nullable', 'required_with:salary_min', Rule::enum(SalaryPeriod::class)],
            'work_modes' => ['array'],
            'work_modes.*' => [Rule::enum(WorkMode::class)],
            'employment_types' => ['array'],
            'employment_types.*' => [Rule::enum(EmploymentType::class)],
        ];
    }

    public function toData(): ProfileData
    {
        /** @var list<array{code: string, level: string}> $languages */
        $languages = $this->array('languages');

        return new ProfileData(
            headline: $this->filled('headline') ? $this->string('headline')->trim()->toString() : null,
            phone: $this->filled('phone') ? $this->string('phone')->trim()->toString() : null,
            country: $this->filled('country') ? $this->string('country')->toString() : null,
            city: $this->filled('city') ? $this->string('city')->trim()->toString() : null,
            githubUsername: $this->filled('github_username') ? $this->string('github_username')->toString() : null,
            linkedinUrl: $this->filled('linkedin_url') ? $this->string('linkedin_url')->toString() : null,
            portfolioUrl: $this->filled('portfolio_url') ? $this->string('portfolio_url')->toString() : null,
            languages: array_column(array_map(
                fn (array $language): array => [$language['code'], LanguageLevel::from($language['level'])],
                $languages,
            ), 1, 0),
            salaryMin: $this->filled('salary_min') ? $this->integer('salary_min') : null,
            salaryCurrency: $this->filled('salary_currency') ? $this->string('salary_currency')->toString() : null,
            salaryPeriod: $this->enum('salary_period', SalaryPeriod::class),
            workModes: array_values(array_map(fn (string $mode): WorkMode => WorkMode::from($mode), $this->array('work_modes'))),
            employmentTypes: array_values(array_map(fn (string $type): EmploymentType => EmploymentType::from($type), $this->array('employment_types'))),
        );
    }
}
