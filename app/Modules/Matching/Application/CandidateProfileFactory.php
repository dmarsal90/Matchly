<?php

declare(strict_types=1);

namespace App\Modules\Matching\Application;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Matching\Domain\Data\CandidateProfile;
use App\Modules\Profiles\Application\Queries\SkillProfileQuery;
use App\Modules\Profiles\Domain\Data\SearchProfile;
use App\Modules\Profiles\Domain\Enums\ImportStatus;
use App\Modules\Profiles\Domain\Enums\LanguageLevel;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;

/** Builds the scorer's view of a user from their profile, merged skills and confirmed imports. */
final readonly class CandidateProfileFactory
{
    public function __construct(private SkillProfileQuery $skills) {}

    public function for(User $user): CandidateProfile
    {
        $profile = $user->profile()->firstOrNew();

        $skills = [];
        foreach ($this->skills->for($user->id) as $skill) {
            $skills[$skill->skill] = $skill;
        }

        $languages = [];
        foreach ($profile->languages ?? [] as $language) {
            $level = LanguageLevel::tryFrom($language['level']);
            if ($level !== null) {
                $languages[$language['code']] = $level;
            }
        }

        // Without target roles, the search profile's suggested roles stand in.
        $roles = $profile->targetRoles() !== [] ? $profile->targetRoles() : ($profile->searchProfile()->roles ?? []);

        [$degrees, $certifications] = $this->education($user->id);

        return new CandidateProfile(
            userId: $user->id,
            skills: $skills,
            yearsExperience: $profile->years_experience,
            seniority: $profile->seniority,
            roles: $roles,
            country: $profile->country,
            regions: $profile->workableRegions(),
            utcOffsetMin: $profile->utc_offset_min,
            utcOffsetMax: $profile->utc_offset_max,
            workModes: $profile->workModes(),
            employmentTypes: $profile->employmentTypes(),
            languages: $languages,
            salaryAnnualMin: SearchProfile::annualSalary($profile->salary_min, $profile->salary_period ?? SalaryPeriod::Year),
            salaryCurrency: $profile->salary_currency,
            dealbreakers: $profile->dealbreakerList(),
            degrees: $degrees,
            certifications: $certifications,
        );
    }

    /**
     * Degrees and certifications from confirmed LinkedIn/CV imports.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private function education(int $userId): array
    {
        $degrees = [];
        $certifications = [];

        $imports = ProfileImport::query()->where('user_id', $userId)->where('status', ImportStatus::Confirmed)->get();
        foreach ($imports as $import) {
            $payload = $import->payload ?? [];
            foreach ((array) ($payload['education'] ?? []) as $item) {
                if (is_array($item) && filled($item['degree'] ?? $item['school'] ?? null)) {
                    $degrees[] = (string) ($item['degree'] ?? $item['school']);
                }
            }
            foreach ((array) ($payload['certifications'] ?? []) as $item) {
                if (is_array($item) && filled($item['name'] ?? null)) {
                    $certifications[] = (string) $item['name'];
                }
            }
        }

        return [array_values(array_unique($degrees)), array_values(array_unique($certifications))];
    }
}
