<?php

declare(strict_types=1);

namespace App\Modules\Matching\Domain\Services;

use App\Modules\Jobs\Domain\Enums\Region;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Matching\Domain\Data\CandidateProfile;
use App\Modules\Matching\Domain\Data\JobRequirements;
use App\Modules\Matching\Domain\Data\JobSnapshot;
use App\Modules\Matching\Domain\Data\MatchResult;
use App\Modules\Profiles\Domain\Data\MergedSkill;
use App\Modules\Profiles\Domain\Enums\Dealbreaker;
use App\Modules\Profiles\Domain\Enums\LanguageLevel;

/**
 * Deterministic, explainable compatibility score (0-100).
 *
 * Each category yields a ratio in [0, 1] that is weighted by
 * config('matching.weights'). When the posting says nothing about a
 * category the ratio is neutral and marked unknown rather than penalising
 * the candidate for the posting's silence. Hard constraints (residence-only
 * postings, dealbreakers) make the job ineligible instead of lowering the
 * score. Only the candidate's professional data is ever read.
 */
final readonly class MatchScorer
{
    private const float UNKNOWN = 0.5;

    /**
     * @param  array<string, int|float>  $weights  category => weight
     * @param  array{high: int, medium: int}  $levels
     * @param  array<string, int>  $seniorityYears
     */
    public function __construct(
        private array $weights,
        private array $levels = ['high' => 75, 'medium' => 50],
        private float $requiredShare = 0.75,
        private float $declaredOnlyFactor = 0.8,
        private array $seniorityYears = [],
    ) {}

    public function score(CandidateProfile $candidate, JobSnapshot $job, JobRequirements $requirements): MatchResult
    {
        $reasons = [];
        $suggestions = [];

        [$skillsRatio, $skillsKnown, $matched, $missing] = $this->skills($candidate, $requirements);
        [$experienceRatio, $experienceKnown, $have, $need] = $this->experience($candidate, $job, $requirements);
        [$roleRatio, $roleKnown] = $this->role($candidate, $job, $requirements);
        [$locationRatio, $locationKnown] = $this->location($candidate, $job, $requirements, $reasons);
        [$languagesRatio, $languagesKnown, $missingLanguages] = $this->languages($candidate, $requirements);
        [$salaryRatio, $salaryKnown] = $this->salary($candidate, $job, $reasons);
        [$educationRatio, $educationKnown] = $this->education($candidate, $requirements);

        if ($candidate->has(Dealbreaker::EmploymentTypeStrict) && $job->employmentType !== null
            && $candidate->employmentTypes !== [] && ! in_array($job->employmentType, $candidate->employmentTypes, true)) {
            $reasons[] = ['key' => 'employment_type', 'params' => ['type' => $job->employmentType->value]];
        }

        $ratios = [
            'skills' => [$skillsRatio, $skillsKnown],
            'experience' => [$experienceRatio, $experienceKnown],
            'role' => [$roleRatio, $roleKnown],
            'location' => [$locationRatio, $locationKnown],
            'languages' => [$languagesRatio, $languagesKnown],
            'salary' => [$salaryRatio, $salaryKnown],
            'education' => [$educationRatio, $educationKnown],
        ];

        $totalWeight = array_sum(array_map(fn (int|float $weight): float => max(0.0, (float) $weight), $this->weights)) ?: 1.0;
        $breakdown = [];
        $points = 0.0;
        foreach ($ratios as $category => [$ratio, $known]) {
            $max = 100 * max(0.0, (float) ($this->weights[$category] ?? 0)) / $totalWeight;
            $earned = $max * max(0.0, min(1.0, $ratio));
            $points += $earned;
            $breakdown[$category] = ['points' => round($earned, 1), 'max' => round($max, 1), 'ratio' => round($ratio, 2), 'known' => $known];
        }

        $score = (int) round($points);

        // Concrete next steps, most useful first.
        $missingRequired = array_values(array_filter($missing, fn (array $skill): bool => $skill['required']));
        if ($missingRequired !== []) {
            $suggestions[] = ['key' => 'missing_skills', 'params' => ['skills' => $this->names(array_slice($missingRequired, 0, 3))]];
        }
        $declaredOnly = array_values(array_filter($matched, fn (array $skill): bool => $skill['required'] && ! $skill['evidenced']));
        if ($declaredOnly !== []) {
            $suggestions[] = ['key' => 'add_evidence', 'params' => ['skills' => $this->names(array_slice($declaredOnly, 0, 3))]];
        }
        foreach ($matched as $skill) {
            $merged = $candidate->skills[$skill['skill']] ?? null;
            if ($merged !== null && $merged->levelGap !== null && abs($merged->levelGap) >= 2) {
                $suggestions[] = ['key' => 'review_level', 'params' => ['skill' => $merged->name]];
                break;
            }
        }
        if ($have !== null && $need !== null && $have < $need) {
            $suggestions[] = ['key' => 'experience_gap', 'params' => ['have' => (string) $have, 'need' => (string) $need]];
        }
        foreach ($missingLanguages as $code) {
            $suggestions[] = ['key' => 'language', 'params' => ['language' => $code]];
        }
        if ($requirements->asksFor['cover_letter']) {
            $suggestions[] = ['key' => 'cover_letter', 'params' => []];
        }

        return new MatchResult(
            score: $score,
            level: $score >= $this->levels['high'] ? MatchResult::HIGH : ($score >= $this->levels['medium'] ? MatchResult::MEDIUM : MatchResult::LOW),
            eligible: $reasons === [],
            ineligibleReasons: $reasons,
            breakdown: $breakdown,
            matchedSkills: $matched,
            missingSkills: $missing,
            suggestions: $suggestions,
        );
    }

    /**
     * Evidenced skills count fully; declared-only ones count less. A level
     * below 3 or fewer years than the posting asks for count partially.
     *
     * @return array{0: float, 1: bool, 2: list<array{skill: string, name: string, required: bool, evidenced: bool, level: int|null}>, 3: list<array{skill: string, name: string, required: bool}>}
     */
    private function skills(CandidateProfile $candidate, JobRequirements $requirements): array
    {
        if (! $requirements->hasSkills()) {
            return [self::UNKNOWN, false, [], []];
        }

        $matched = [];
        $missing = [];
        $credits = ['required' => [], 'nice' => []];

        $wanted = [
            ...array_map(fn (array $skill): array => [...$skill, 'required' => true], $requirements->requiredSkills),
            ...array_map(fn (array $skill): array => [...$skill, 'min_years' => null, 'required' => false], $requirements->niceToHaveSkills),
        ];

        foreach ($wanted as $skill) {
            $bucket = $skill['required'] ? 'required' : 'nice';
            $have = $candidate->skills[$skill['skill']] ?? null;

            if ($have === null) {
                $credits[$bucket][] = 0.0;
                $missing[] = ['skill' => $skill['skill'], 'name' => $skill['name'], 'required' => $skill['required']];

                continue;
            }

            $credits[$bucket][] = $this->credit($have, $skill['min_years']);
            $matched[] = ['skill' => $skill['skill'], 'name' => $have->name, 'required' => $skill['required'], 'evidenced' => $have->hasEvidence(), 'level' => $have->level];
        }

        $average = fn (array $values): ?float => $values === [] ? null : array_sum($values) / count($values);
        $required = $average($credits['required']);
        $nice = $average($credits['nice']);

        $ratio = match (true) {
            $required !== null && $nice !== null => $this->requiredShare * $required + (1 - $this->requiredShare) * $nice,
            $required !== null => $required,
            default => (float) $nice,
        };

        return [$ratio, true, $matched, $missing];
    }

    private function credit(MergedSkill $skill, ?float $minYears): float
    {
        $credit = $skill->level === null ? 0.7 : min(1.0, $skill->level / 3);

        if ($minYears !== null && $minYears > 0 && $skill->years !== null && $skill->years < $minYears) {
            $credit *= max(0.5, $skill->years / $minYears);
        }

        return $credit * ($skill->hasEvidence() ? 1.0 : $this->declaredOnlyFactor);
    }

    /**
     * @return array{0: float, 1: bool, 2: int|null, 3: int|null}
     */
    private function experience(CandidateProfile $candidate, JobSnapshot $job, JobRequirements $requirements): array
    {
        $skillYears = array_filter(array_column($requirements->requiredSkills, 'min_years'));
        $seniority = $requirements->seniority ?? $job->seniority;

        $need = $requirements->minYearsExperience
            ?? ($skillYears !== [] ? (int) ceil(max($skillYears)) : null)
            ?? ($seniority !== null ? ($this->seniorityYears[$seniority->value] ?? null) : null);

        $have = $candidate->yearsExperience;
        if ($have === null) {
            $years = array_filter(array_map(fn (MergedSkill $skill): ?float => $skill->years, $candidate->skills));
            $have = $years !== [] ? (int) floor(max($years)) : null;
        }

        if ($need === null) {
            return [$have !== null ? 1.0 : self::UNKNOWN, false, $have, null];
        }
        if ($have === null) {
            return [self::UNKNOWN, true, null, $need];
        }

        return [$need === 0 ? 1.0 : min(1.0, $have / $need), true, $have, $need];
    }

    /**
     * @return array{0: float, 1: bool}
     */
    private function role(CandidateProfile $candidate, JobSnapshot $job, JobRequirements $requirements): array
    {
        $role = match (true) {
            $job->role === null || $candidate->roles === [] => null,
            in_array($job->role, $candidate->roles, true) => 1.0,
            in_array($job->role->category(), array_map(fn ($role) => $role->category(), $candidate->roles), true) => 0.5,
            default => 0.0,
        };

        $wanted = $requirements->seniority ?? $job->seniority;
        $seniority = null;
        if ($wanted !== null && $candidate->seniority !== null) {
            $seniority = match (abs($wanted->rank() - $candidate->seniority->rank())) {
                0 => 1.0,
                1 => 0.6,
                2 => 0.2,
                default => 0.0,
            };
        }

        if ($role === null && $seniority === null) {
            return [self::UNKNOWN, false];
        }

        return [0.6 * ($role ?? 0.7) + 0.4 * ($seniority ?? 0.7), true];
    }

    /**
     * @param  list<array{key: string, params: array<string, string>}>  $reasons
     * @return array{0: float, 1: bool}
     */
    private function location(CandidateProfile $candidate, JobSnapshot $job, JobRequirements $requirements, array &$reasons): array
    {
        if ($job->workMode !== WorkMode::Remote && $candidate->has(Dealbreaker::RemoteOnly)) {
            $reasons[] = ['key' => 'remote_only', 'params' => ['mode' => $job->workMode->value]];
        }
        $mode = $candidate->workModes === [] || in_array($job->workMode, $candidate->workModes, true) ? 1.0 : 0.3;

        $countries = array_values(array_unique([...$requirements->countries, ...array_filter($job->allowedRegions, fn (string $value): bool => strlen($value) === 2)]));
        $regions = array_values(array_unique([...$requirements->regions, ...array_filter(array_map(Region::tryFrom(...), $job->allowedRegions))], SORT_REGULAR));
        $restricted = $countries !== [] || $regions !== [];

        $region = 1.0;
        $known = $restricted;
        if ($restricted) {
            $fits = $this->fitsLocation($candidate, $countries, $regions);
            if ($fits === null) {
                $region = self::UNKNOWN;
            } elseif (! $fits) {
                $region = 0.0;
                if ($requirements->locationExclusive) {
                    $reasons[] = ['key' => 'location', 'params' => ['allowed' => implode(', ', [...$countries, ...array_map(fn (Region $item): string => $item->value, $regions)])]];
                } elseif ($candidate->has(Dealbreaker::RegionStrict)) {
                    $reasons[] = ['key' => 'region_strict', 'params' => []];
                }
            }
        }

        $timezone = 1.0;
        if ($requirements->utcOffsetMin !== null && $candidate->utcOffsetMin !== null) {
            $known = true;
            $jobMax = $requirements->utcOffsetMax ?? $requirements->utcOffsetMin;
            $candidateMax = $candidate->utcOffsetMax ?? $candidate->utcOffsetMin;
            $gap = max($requirements->utcOffsetMin, $candidate->utcOffsetMin) - min($jobMax, $candidateMax);
            $timezone = $gap <= 0 ? 1.0 : max(0.0, 1 - $gap / 6);
        }

        return [0.5 * $region + 0.3 * $timezone + 0.2 * $mode, $known];
    }

    /**
     * Whether the candidate may apply from where they work: true, false, or
     * null when it cannot be told (e.g. they said "EU" and the posting lists
     * specific EU countries).
     *
     * @param  list<string>  $countries
     * @param  list<Region>  $regions
     */
    private function fitsLocation(CandidateProfile $candidate, array $countries, array $regions): ?bool
    {
        if ($candidate->country !== null) {
            if (in_array($candidate->country, $countries, true)) {
                return true;
            }
            foreach ($regions as $region) {
                if (RegionMembership::contains($region, $candidate->country)) {
                    return true;
                }
            }
        }

        if ($candidate->regions === []) {
            return $candidate->country === null ? null : false;
        }

        $uncertain = false;
        foreach ($candidate->regions as $from) {
            foreach ($regions as $allowed) {
                if (RegionMembership::within($from, $allowed)) {
                    return true;
                }
                // "Europe" vs an EU-only posting: maybe.
                if (RegionMembership::within($allowed, $from)) {
                    $uncertain = true;
                }
            }
            foreach ($countries as $country) {
                if (RegionMembership::contains($from, $country)) {
                    $uncertain = true;
                }
            }
        }

        return $uncertain ? null : false;
    }

    /**
     * @return array{0: float, 1: bool, 2: list<string>}
     */
    private function languages(CandidateProfile $candidate, JobRequirements $requirements): array
    {
        $required = array_values(array_filter($requirements->languages, fn (array $language): bool => $language['required']));
        if ($required === []) {
            return [1.0, false, []];
        }

        $credits = [];
        $missing = [];
        foreach ($required as $language) {
            $have = $candidate->languages[$language['code']] ?? null;
            $need = $language['level'] ?? LanguageLevel::B2;

            if ($have === null) {
                $credits[] = 0.0;
                $missing[] = $language['code'];
            } else {
                $credits[] = $have->rank() >= $need->rank() ? 1.0 : 0.5;
            }
        }

        return [array_sum($credits) / count($credits), true, $missing];
    }

    /**
     * @param  list<array{key: string, params: array<string, string>}>  $reasons
     * @return array{0: float, 1: bool}
     */
    private function salary(CandidateProfile $candidate, JobSnapshot $job, array &$reasons): array
    {
        if (! $job->hasSalary()) {
            if ($candidate->has(Dealbreaker::SalaryDisclosed)) {
                $reasons[] = ['key' => 'salary_disclosed', 'params' => []];
            }

            return [self::UNKNOWN, false];
        }

        // Currencies are never converted: different currencies are not comparable.
        if ($candidate->salaryAnnualMin === null || $candidate->salaryCurrency === null || $job->salaryCurrency !== $candidate->salaryCurrency) {
            return [self::UNKNOWN, false];
        }

        $offered = (float) ($job->salaryAnnualMax ?? $job->salaryAnnualMin);
        if ($offered >= $candidate->salaryAnnualMin) {
            return [1.0, true];
        }

        if ($candidate->has(Dealbreaker::SalaryFloor)) {
            $reasons[] = ['key' => 'salary_floor', 'params' => []];
        }

        return [max(0.0, $offered / $candidate->salaryAnnualMin), true];
    }

    /**
     * @return array{0: float, 1: bool}
     */
    private function education(CandidateProfile $candidate, JobRequirements $requirements): array
    {
        $parts = [];
        if ($requirements->degreeRequired) {
            // An empty list usually means "not imported", not "no degree".
            $parts[] = $candidate->degrees !== [] ? 1.0 : self::UNKNOWN;
        }

        if ($requirements->certifications !== []) {
            $held = array_map(mb_strtolower(...), $candidate->certifications);
            $met = 0;
            foreach ($requirements->certifications as $certification) {
                $needle = mb_strtolower($certification);
                foreach ($held as $have) {
                    if (str_contains($have, $needle) || str_contains($needle, $have)) {
                        $met++;
                        break;
                    }
                }
            }
            $parts[] = $met / count($requirements->certifications);
        }

        return $parts === [] ? [1.0, false] : [array_sum($parts) / count($parts), true];
    }

    /**
     * @param  list<array{name: string}>  $skills
     */
    private function names(array $skills): string
    {
        return implode(', ', array_map(fn (array $skill): string => $skill['name'], $skills));
    }
}
