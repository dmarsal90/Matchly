<?php

declare(strict_types=1);

namespace App\Modules\Matching\Domain\Data;

use App\Modules\Jobs\Domain\Enums\Region;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Profiles\Domain\Enums\LanguageLevel;

/**
 * What a posting asks for, structured. Built by rules (always) and refined
 * by the AI (when available); stored on the job so it is computed once.
 */
final readonly class JobRequirements
{
    public const string SOURCE_RULES = 'rules';

    public const string SOURCE_AI = 'ai';

    /**
     * @param  list<array{skill: string, name: string, min_years: float|null}>  $requiredSkills
     * @param  list<array{skill: string, name: string}>  $niceToHaveSkills
     * @param  list<array{code: string, level: LanguageLevel|null, required: bool}>  $languages
     * @param  list<string>  $certifications
     * @param  list<string>  $countries  ISO 3166-1 alpha-2
     * @param  list<Region>  $regions
     * @param  array{cover_letter: bool, github: bool, portfolio: bool, salary_expectations: bool, additional_questions: bool}  $asksFor
     */
    public function __construct(
        public string $source,
        public array $requiredSkills = [],
        public array $niceToHaveSkills = [],
        public ?int $minYearsExperience = null,
        public ?Seniority $seniority = null,
        public array $languages = [],
        public bool $degreeRequired = false,
        public ?string $degreeField = null,
        public array $certifications = [],
        public bool $locationExclusive = false,
        public array $countries = [],
        public array $regions = [],
        public ?int $utcOffsetMin = null,
        public ?int $utcOffsetMax = null,
        public array $asksFor = ['cover_letter' => false, 'github' => false, 'portfolio' => false, 'salary_expectations' => false, 'additional_questions' => false],
        public ?string $language = null,
        public ?string $promptVersion = null,
    ) {}

    public function hasSkills(): bool
    {
        return $this->requiredSkills !== [] || $this->niceToHaveSkills !== [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $list = fn (string $key): array => array_values(array_filter((array) ($data[$key] ?? []), 'is_array'));
        $location = (array) ($data['location'] ?? []);
        $education = (array) ($data['education'] ?? []);
        $asks = (array) ($data['asks_for'] ?? []);
        $int = fn (mixed $value): ?int => is_numeric($value) ? (int) $value : null;

        return new self(
            source: is_string($data['source'] ?? null) ? $data['source'] : self::SOURCE_RULES,
            requiredSkills: array_values(array_map(fn (array $skill): array => [
                'skill' => (string) $skill['skill'],
                'name' => (string) ($skill['name'] ?? $skill['skill']),
                'min_years' => is_numeric($skill['min_years'] ?? null) ? (float) $skill['min_years'] : null,
            ], array_filter($list('required_skills'), fn (array $skill): bool => is_string($skill['skill'] ?? null)))),
            niceToHaveSkills: array_values(array_map(fn (array $skill): array => [
                'skill' => (string) $skill['skill'],
                'name' => (string) ($skill['name'] ?? $skill['skill']),
            ], array_filter($list('nice_to_have_skills'), fn (array $skill): bool => is_string($skill['skill'] ?? null)))),
            minYearsExperience: $int($data['min_years_experience'] ?? null),
            seniority: is_string($data['seniority'] ?? null) ? Seniority::tryFrom($data['seniority']) : null,
            languages: array_values(array_map(fn (array $language): array => [
                'code' => (string) $language['code'],
                'level' => is_string($language['level'] ?? null) ? LanguageLevel::tryFrom($language['level']) : null,
                'required' => (bool) ($language['required'] ?? true),
            ], array_filter($list('languages'), fn (array $language): bool => is_string($language['code'] ?? null)))),
            degreeRequired: (bool) ($education['degree_required'] ?? false),
            degreeField: is_string($education['field'] ?? null) ? $education['field'] : null,
            certifications: array_values(array_filter((array) ($data['certifications'] ?? []), 'is_string')),
            locationExclusive: (bool) ($location['exclusive'] ?? false),
            countries: array_values(array_filter((array) ($location['countries'] ?? []), fn (mixed $code): bool => is_string($code) && strlen($code) === 2)),
            regions: array_values(array_filter(array_map(fn (mixed $region): ?Region => is_string($region) ? Region::tryFrom($region) : null, (array) ($location['regions'] ?? [])))),
            utcOffsetMin: $int($location['utc_offset_min'] ?? null),
            utcOffsetMax: $int($location['utc_offset_max'] ?? null),
            asksFor: [
                'cover_letter' => (bool) ($asks['cover_letter'] ?? false),
                'github' => (bool) ($asks['github'] ?? false),
                'portfolio' => (bool) ($asks['portfolio'] ?? false),
                'salary_expectations' => (bool) ($asks['salary_expectations'] ?? false),
                'additional_questions' => (bool) ($asks['additional_questions'] ?? false),
            ],
            language: is_string($data['language'] ?? null) ? $data['language'] : null,
            promptVersion: is_string($data['prompt_version'] ?? null) ? $data['prompt_version'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'prompt_version' => $this->promptVersion,
            'required_skills' => $this->requiredSkills,
            'nice_to_have_skills' => $this->niceToHaveSkills,
            'min_years_experience' => $this->minYearsExperience,
            'seniority' => $this->seniority?->value,
            'languages' => array_map(fn (array $language): array => [...$language, 'level' => $language['level']?->value], $this->languages),
            'education' => ['degree_required' => $this->degreeRequired, 'field' => $this->degreeField],
            'certifications' => $this->certifications,
            'location' => [
                'exclusive' => $this->locationExclusive,
                'countries' => $this->countries,
                'regions' => array_map(fn (Region $region): string => $region->value, $this->regions),
                'utc_offset_min' => $this->utcOffsetMin,
                'utc_offset_max' => $this->utcOffsetMax,
            ],
            'asks_for' => $this->asksFor,
            'language' => $this->language,
        ];
    }
}
