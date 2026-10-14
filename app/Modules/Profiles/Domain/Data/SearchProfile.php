<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Domain\Data;

use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use BackedEnum;

/**
 * Suggested focus of the job search: what to look for, built from the merged
 * profile and editable by the user. Its filters map one-to-one onto the
 * listing's query string.
 */
final readonly class SearchProfile
{
    /**
     * @param  list<JobRole>  $roles
     * @param  list<string>  $technologies  skill slugs, strongest first
     * @param  list<Seniority>  $seniorities
     * @param  list<WorkMode>  $workModes
     * @param  list<EmploymentType>  $employmentTypes
     * @param  int|null  $salaryMin  annual
     */
    public function __construct(
        public array $roles,
        public array $technologies,
        public array $seniorities,
        public array $workModes,
        public array $employmentTypes,
        public ?int $salaryMin,
        public ?string $salaryCurrency,
        public bool $edited = false,
    ) {}

    public static function annualSalary(?int $amount, ?SalaryPeriod $period): ?int
    {
        return $amount !== null && $amount > 0 ? $amount * ($period ?? SalaryPeriod::Year)->annualFactor() : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $list = fn (string $key): array => array_values(array_filter((array) ($data[$key] ?? []), 'is_string'));

        return new self(
            roles: array_values(array_filter(array_map(JobRole::tryFrom(...), $list('roles')))),
            technologies: $list('technologies'),
            seniorities: array_values(array_filter(array_map(Seniority::tryFrom(...), $list('seniorities')))),
            workModes: array_values(array_filter(array_map(WorkMode::tryFrom(...), $list('work_modes')))),
            employmentTypes: array_values(array_filter(array_map(EmploymentType::tryFrom(...), $list('employment_types')))),
            salaryMin: is_numeric($data['salary_min'] ?? null) ? (int) $data['salary_min'] : null,
            salaryCurrency: is_string($data['salary_currency'] ?? null) ? $data['salary_currency'] : null,
            edited: (bool) ($data['edited'] ?? false),
        );
    }

    /**
     * @return array{roles: list<string>, technologies: list<string>, seniorities: list<string>, work_modes: list<string>, employment_types: list<string>, salary_min: int|null, salary_currency: string|null, edited: bool}
     */
    public function toArray(): array
    {
        return [
            'roles' => array_map(fn (JobRole $role): string => $role->value, $this->roles),
            'technologies' => $this->technologies,
            'seniorities' => array_map(fn (Seniority $seniority): string => $seniority->value, $this->seniorities),
            'work_modes' => array_map(fn (WorkMode $mode): string => $mode->value, $this->workModes),
            'employment_types' => array_map(fn (EmploymentType $type): string => $type->value, $this->employmentTypes),
            'salary_min' => $this->salaryMin,
            'salary_currency' => $this->salaryCurrency,
            'edited' => $this->edited,
        ];
    }

    /**
     * The job listing's query string for this profile.
     *
     * @return array<string, string>
     */
    public function toListingQuery(): array
    {
        $join = fn (array $values): string => implode(',', array_map(fn (BackedEnum $value): string => (string) $value->value, $values));

        return array_filter([
            'role' => $join($this->roles),
            'seniority' => $join($this->seniorities),
            'work_mode' => $join($this->workModes),
            'type' => $join($this->employmentTypes),
            'salary_min' => $this->salaryMin !== null ? (string) $this->salaryMin : '',
            'currency' => $this->salaryMin !== null ? (string) $this->salaryCurrency : '',
        ], fn (string $value): bool => $value !== '');
    }

    public function isEmpty(): bool
    {
        return $this->roles === [] && $this->technologies === [] && $this->seniorities === []
            && $this->workModes === [] && $this->employmentTypes === [] && $this->salaryMin === null;
    }
}
