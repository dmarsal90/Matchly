<?php

declare(strict_types=1);

use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Profiles\Domain\Data\SearchProfile;
use App\Modules\Profiles\Domain\Data\SkillClaim;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use App\Modules\Profiles\Domain\Services\SearchProfileBuilder;
use App\Modules\Profiles\Domain\Services\SkillMerger;

function buildSearchProfile(array $claims, array $roles = [], ?int $salary = null, ?SalaryPeriod $period = null): SearchProfile
{
    $merged = (new SkillMerger(['declared' => 0.5, 'cv' => 0.7, 'linkedin' => 0.75, 'github' => 0.9]))->merge($claims);

    return (new SearchProfileBuilder(catalog()))->build($roles, $merged, [WorkMode::Remote], [EmploymentType::FullTime], $salary, $salary !== null ? 'EUR' : null, $period);
}

it('uses the roles the user chose', function (): void {
    $profile = buildSearchProfile([new SkillClaim('react', 'React', SkillSource::Declared, level: 5)], roles: [JobRole::Mobile]);

    expect($profile->roles)->toBe([JobRole::Mobile]);
});

it('infers fullstack from strong frontend and backend skills', function (): void {
    $profile = buildSearchProfile([
        new SkillClaim('vue', 'Vue', SkillSource::Github, level: 4),
        new SkillClaim('typescript', 'TypeScript', SkillSource::Github, level: 4),
        new SkillClaim('laravel', 'Laravel', SkillSource::Github, level: 5),
        new SkillClaim('php', 'PHP', SkillSource::Declared, level: 5),
    ]);

    expect($profile->roles[0])->toBe(JobRole::Fullstack)
        ->and(count($profile->roles))->toBeLessThanOrEqual(3);
});

it('infers a single role for a focused profile', function (): void {
    $profile = buildSearchProfile([
        new SkillClaim('kubernetes', 'Kubernetes', SkillSource::Github, level: 4),
        new SkillClaim('terraform', 'Terraform', SkillSource::Github, level: 4),
        new SkillClaim('aws', 'AWS', SkillSource::Linkedin, level: 4),
    ]);

    expect($profile->roles)->toBe([JobRole::DevOps]);
});

it('leaves weak, unbacked claims out of the technologies', function (): void {
    $profile = buildSearchProfile([
        new SkillClaim('php', 'PHP', SkillSource::Declared, level: 4),
        new SkillClaim('cobol', 'COBOL', SkillSource::Declared, level: 1),
        new SkillClaim('rust', 'Rust', SkillSource::Cv),
    ]);

    expect($profile->technologies)->toBe(['php', 'rust']);
});

it('annualises the salary and never filters on seniority', function (): void {
    $profile = buildSearchProfile([new SkillClaim('php', 'PHP', SkillSource::Declared, level: 4)], salary: 4000, period: SalaryPeriod::Month);

    expect($profile->salaryMin)->toBe(48000)
        ->and($profile->salaryCurrency)->toBe('EUR')
        ->and($profile->seniorities)->toBe([]);
});

it('maps onto the job listing query string', function (): void {
    $profile = new SearchProfile(
        roles: [JobRole::Backend, JobRole::Fullstack],
        technologies: ['php'],
        seniorities: [Seniority::Senior],
        workModes: [WorkMode::Remote],
        employmentTypes: [EmploymentType::FullTime, EmploymentType::Contract],
        salaryMin: 50000,
        salaryCurrency: 'EUR',
    );

    expect($profile->toListingQuery())->toBe([
        'role' => 'backend,fullstack',
        'seniority' => 'senior',
        'work_mode' => 'remote',
        'type' => 'full_time,contract',
        'salary_min' => '50000',
        'currency' => 'EUR',
    ]);
});

it('round-trips through its array form and drops unknown values', function (): void {
    $profile = SearchProfile::fromArray(['roles' => ['backend', 'astronaut'], 'technologies' => ['php', 3], 'work_modes' => ['remote'], 'edited' => true]);

    expect($profile->roles)->toBe([JobRole::Backend])
        ->and($profile->technologies)->toBe(['php'])
        ->and($profile->edited)->toBeTrue()
        ->and(SearchProfile::fromArray($profile->toArray()))->toEqual($profile);
});
