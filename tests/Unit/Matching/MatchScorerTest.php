<?php

declare(strict_types=1);

use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\Region;
use App\Modules\Jobs\Domain\Enums\RemoteScope;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Matching\Domain\Data\CandidateProfile;
use App\Modules\Matching\Domain\Data\JobRequirements;
use App\Modules\Matching\Domain\Data\JobSnapshot;
use App\Modules\Matching\Domain\Data\MatchResult;
use App\Modules\Matching\Domain\Services\MatchScorer;
use App\Modules\Profiles\Domain\Data\SkillClaim;
use App\Modules\Profiles\Domain\Enums\Dealbreaker;
use App\Modules\Profiles\Domain\Enums\LanguageLevel;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use App\Modules\Profiles\Domain\Services\SkillMerger;

function matchScorer(): MatchScorer
{
    $config = require __DIR__.'/../../../config/matching.php';

    return new MatchScorer(
        ['skills' => 40, 'experience' => 20, 'role' => 15, 'location' => 10, 'languages' => 5, 'salary' => 5, 'education' => 5],
        ['high' => 75, 'medium' => 50],
        0.75,
        0.8,
        $config['seniority_years'],
    );
}

/**
 * @param  list<SkillClaim>  $claims
 */
function makeCandidate(array $claims = [], array $overrides = []): CandidateProfile
{
    $skills = [];
    foreach ((new SkillMerger(['declared' => 0.5, 'cv' => 0.7, 'linkedin' => 0.75, 'github' => 0.9]))->merge($claims) as $skill) {
        $skills[$skill->skill] = $skill;
    }

    return new CandidateProfile(...[
        'userId' => 1,
        'skills' => $skills,
        'yearsExperience' => 6,
        'seniority' => Seniority::Senior,
        'roles' => [JobRole::Backend],
        'country' => 'ES',
        'workModes' => [WorkMode::Remote],
        'employmentTypes' => [EmploymentType::FullTime],
        'languages' => ['es' => LanguageLevel::Native, 'en' => LanguageLevel::C1],
        'salaryAnnualMin' => 50000,
        'salaryCurrency' => 'EUR',
        ...$overrides,
    ]);
}

function makeJob(array $overrides = []): JobSnapshot
{
    return new JobSnapshot(...[
        'id' => 1,
        'title' => 'Senior Backend Engineer',
        'text' => '',
        'role' => JobRole::Backend,
        'seniority' => Seniority::Senior,
        'workMode' => WorkMode::Remote,
        'remoteScope' => RemoteScope::Worldwide,
        'employmentType' => EmploymentType::FullTime,
        'salaryAnnualMin' => 55000.0,
        'salaryAnnualMax' => 70000.0,
        'salaryCurrency' => 'EUR',
        ...$overrides,
    ]);
}

function makeRequirements(array $overrides = []): JobRequirements
{
    return new JobRequirements(...[
        'source' => JobRequirements::SOURCE_AI,
        'requiredSkills' => [
            ['skill' => 'php', 'name' => 'PHP', 'min_years' => null],
            ['skill' => 'laravel', 'name' => 'Laravel', 'min_years' => 3.0],
        ],
        'niceToHaveSkills' => [['skill' => 'vue', 'name' => 'Vue']],
        'minYearsExperience' => 5,
        'languages' => [['code' => 'en', 'level' => LanguageLevel::B2, 'required' => true]],
        ...$overrides,
    ]);
}

$strongClaims = fn (): array => [
    new SkillClaim('php', 'PHP', SkillSource::Github, level: 5, years: 8),
    new SkillClaim('laravel', 'Laravel', SkillSource::Github, level: 4, years: 5),
    new SkillClaim('vue', 'Vue', SkillSource::Linkedin, level: 3, years: 2),
];

it('scores a strong, fully evidenced candidate high and explains every category', function () use ($strongClaims): void {
    $result = matchScorer()->score(makeCandidate($strongClaims()), makeJob(), makeRequirements());

    expect($result->score)->toBe(100)
        ->and($result->level)->toBe(MatchResult::HIGH)
        ->and($result->eligible)->toBeTrue()
        ->and(array_keys($result->breakdown))->toBe(['skills', 'experience', 'role', 'location', 'languages', 'salary', 'education'])
        ->and($result->breakdown['skills'])->toBe(['points' => 40.0, 'max' => 40.0, 'ratio' => 1.0, 'known' => true])
        ->and(array_column($result->matchedSkills, 'skill'))->toBe(['php', 'laravel', 'vue'])
        ->and($result->missingSkills)->toBe([])
        ->and($result->suggestions)->toBe([]);
});

it('weighs evidenced skills more than declared ones', function (): void {
    $evidenced = matchScorer()->score(makeCandidate([
        new SkillClaim('php', 'PHP', SkillSource::Github, level: 4),
        new SkillClaim('laravel', 'Laravel', SkillSource::Cv, level: 4),
    ]), makeJob(), makeRequirements(['niceToHaveSkills' => []]));
    $declared = matchScorer()->score(makeCandidate([
        new SkillClaim('php', 'PHP', SkillSource::Declared, level: 4),
        new SkillClaim('laravel', 'Laravel', SkillSource::Declared, level: 4),
    ]), makeJob(), makeRequirements(['niceToHaveSkills' => []]));

    expect($evidenced->breakdown['skills']['ratio'])->toBe(1.0)
        ->and($declared->breakdown['skills']['ratio'])->toBe(0.8)
        ->and($declared->suggestions[0])->toBe(['key' => 'add_evidence', 'params' => ['skills' => 'PHP, Laravel']]);
});

it('lists missing skills and suggests them', function (): void {
    $result = matchScorer()->score(makeCandidate([new SkillClaim('php', 'PHP', SkillSource::Github, level: 4)]), makeJob(), makeRequirements());

    expect($result->missingSkills)->toBe([
        ['skill' => 'laravel', 'name' => 'Laravel', 'required' => true],
        ['skill' => 'vue', 'name' => 'Vue', 'required' => false],
    ])
        ->and($result->suggestions[0])->toBe(['key' => 'missing_skills', 'params' => ['skills' => 'Laravel']])
        ->and($result->missingKeywords())->toBe(['Laravel', 'Vue'])
        ->and($result->breakdown['skills']['ratio'])->toBe(0.38);
});

it('treats synonyms as the same skill through the catalog slugs', function (): void {
    $skill = catalog()->normalize('Vue.js');
    $result = matchScorer()->score(
        makeCandidate([new SkillClaim($skill->slug, $skill->label, SkillSource::Github, level: 4)]),
        makeJob(),
        makeRequirements(['requiredSkills' => [['skill' => catalog()->normalize('VueJS')->slug, 'name' => 'VueJS', 'min_years' => null]], 'niceToHaveSkills' => []]),
    );

    expect($result->breakdown['skills']['ratio'])->toBe(1.0);
});

it('reduces credit for low levels and too few years on a skill', function (): void {
    $result = matchScorer()->score(makeCandidate([
        new SkillClaim('php', 'PHP', SkillSource::Github, level: 2),
        new SkillClaim('laravel', 'Laravel', SkillSource::Github, level: 4, years: 1.5),
    ]), makeJob(), makeRequirements(['niceToHaveSkills' => []]));

    // PHP: 2/3; Laravel: 1 × max(0.5, 1.5 / 3) = 0.5.
    expect($result->breakdown['skills']['ratio'])->toBe(0.58);
});

it('scores experience against the stated years, falling back to the seniority', function () use ($strongClaims): void {
    $junior = matchScorer()->score(makeCandidate($strongClaims(), ['yearsExperience' => 2]), makeJob(), makeRequirements(['minYearsExperience' => 8]));
    $bySeniority = matchScorer()->score(makeCandidate($strongClaims(), ['yearsExperience' => 3]), makeJob(), makeRequirements(['minYearsExperience' => null, 'requiredSkills' => []]));

    expect($junior->breakdown['experience']['ratio'])->toBe(0.25)
        ->and($junior->suggestions)->toContain(['key' => 'experience_gap', 'params' => ['have' => '2', 'need' => '8']])
        ->and($bySeniority->breakdown['experience']['ratio'])->toBe(0.6);
});

it('scores role and seniority', function () use ($strongClaims): void {
    $otherRole = matchScorer()->score(makeCandidate($strongClaims()), makeJob(['role' => JobRole::Designer, 'seniority' => Seniority::Junior]), makeRequirements());
    $sameCategory = matchScorer()->score(makeCandidate($strongClaims()), makeJob(['role' => JobRole::Frontend]), makeRequirements());

    expect($otherRole->breakdown['role']['ratio'])->toBe(0.08)
        ->and($sameCategory->breakdown['role']['ratio'])->toBe(0.7);
});

it('makes residence-restricted jobs ineligible instead of just scoring lower', function () use ($strongClaims): void {
    $usOnly = matchScorer()->score(
        makeCandidate($strongClaims()),
        makeJob(['remoteScope' => RemoteScope::CountryRestricted, 'allowedRegions' => ['US']]),
        makeRequirements(['locationExclusive' => true, 'countries' => ['US']]),
    );

    expect($usOnly->eligible)->toBeFalse()
        ->and($usOnly->ineligibleReasons)->toBe([['key' => 'location', 'params' => ['allowed' => 'US']]])
        ->and($usOnly->score)->toBeGreaterThan(50);
});

it('accepts candidates whose country belongs to an allowed region', function () use ($strongClaims): void {
    $result = matchScorer()->score(
        makeCandidate($strongClaims(), ['country' => 'PT']),
        makeJob(['allowedRegions' => ['EU']]),
        makeRequirements(['locationExclusive' => true, 'regions' => [Region::EuropeanUnion]]),
    );

    expect($result->eligible)->toBeTrue()->and($result->breakdown['location']['ratio'])->toBe(1.0);
});

it('applies dealbreakers as hard constraints', function (array $dealbreakers, array $jobOverrides, string $reason) use ($strongClaims): void {
    $result = matchScorer()->score(makeCandidate($strongClaims(), ['dealbreakers' => $dealbreakers]), makeJob($jobOverrides), makeRequirements());

    expect($result->eligible)->toBeFalse()
        ->and(array_column($result->ineligibleReasons, 'key'))->toContain($reason);
})->with([
    'remote only' => [[Dealbreaker::RemoteOnly], ['workMode' => WorkMode::Hybrid], 'remote_only'],
    'salary disclosed' => [[Dealbreaker::SalaryDisclosed], ['salaryAnnualMin' => null, 'salaryAnnualMax' => null], 'salary_disclosed'],
    'salary floor' => [[Dealbreaker::SalaryFloor], ['salaryAnnualMin' => 30000.0, 'salaryAnnualMax' => 40000.0], 'salary_floor'],
    'contract type' => [[Dealbreaker::EmploymentTypeStrict], ['employmentType' => EmploymentType::Freelance], 'employment_type'],
    'region' => [[Dealbreaker::RegionStrict], ['allowedRegions' => ['LATAM']], 'region_strict'],
]);

it('does not penalise what the posting does not state', function () use ($strongClaims): void {
    $result = matchScorer()->score(makeCandidate($strongClaims()), makeJob(['salaryAnnualMin' => null, 'salaryAnnualMax' => null]), makeRequirements(['languages' => []]));

    expect($result->breakdown['salary'])->toMatchArray(['ratio' => 0.5, 'known' => false])
        ->and($result->breakdown['languages'])->toMatchArray(['ratio' => 1.0, 'known' => false])
        ->and($result->breakdown['education'])->toMatchArray(['ratio' => 1.0, 'known' => false]);
});

it('never converts currencies', function () use ($strongClaims): void {
    $result = matchScorer()->score(makeCandidate($strongClaims()), makeJob(['salaryCurrency' => 'USD', 'salaryAnnualMax' => 10000.0]), makeRequirements());

    expect($result->breakdown['salary'])->toMatchArray(['ratio' => 0.5, 'known' => false]);
});

it('checks required languages and levels', function () use ($strongClaims): void {
    $result = matchScorer()->score(
        makeCandidate($strongClaims(), ['languages' => ['en' => LanguageLevel::B1]]),
        makeJob(),
        makeRequirements(['languages' => [
            ['code' => 'en', 'level' => LanguageLevel::C1, 'required' => true],
            ['code' => 'de', 'level' => null, 'required' => true],
        ]]),
    );

    expect($result->breakdown['languages']['ratio'])->toBe(0.25)
        ->and($result->suggestions)->toContain(['key' => 'language', 'params' => ['language' => 'de']]);
});

it('normalises the weights from configuration', function (): void {
    $skillsOnly = new MatchScorer(['skills' => 1, 'experience' => 0, 'role' => 0, 'location' => 0, 'languages' => 0, 'salary' => 0, 'education' => 0]);
    $result = $skillsOnly->score(makeCandidate([new SkillClaim('php', 'PHP', SkillSource::Github, level: 5)]), makeJob(), makeRequirements(['niceToHaveSkills' => []]));

    expect($result->breakdown['skills']['max'])->toBe(100.0)
        ->and($result->score)->toBe(50);
});

it('bands scores into levels', function (): void {
    $low = matchScorer()->score(
        makeCandidate([], ['yearsExperience' => 1, 'seniority' => Seniority::Junior, 'languages' => []]),
        makeJob(['role' => JobRole::Designer]),
        makeRequirements(),
    );

    expect($low->level)->toBe(MatchResult::LOW);
});

it('matches regions by inclusion and stays neutral when it cannot tell', function () use ($strongClaims): void {
    $euCandidate = makeCandidate($strongClaims(), ['country' => null, 'regions' => [Region::EuropeanUnion]]);
    $exclusive = fn (array $regions, array $countries = []): MatchResult => matchScorer()->score(
        $euCandidate,
        makeJob(['allowedRegions' => [...array_map(fn (Region $region): string => $region->value, $regions), ...$countries]]),
        makeRequirements(['locationExclusive' => true, 'regions' => $regions, 'countries' => $countries]),
    );

    expect($exclusive([Region::Europe])->eligible)->toBeTrue()
        ->and($exclusive([Region::Emea])->breakdown['location']['ratio'])->toBe(1.0)
        // EU candidate, Germany-only posting: maybe.
        ->and($exclusive([], ['DE'])->eligible)->toBeTrue()
        ->and($exclusive([], ['DE'])->breakdown['location']['ratio'])->toBe(0.75)
        ->and($exclusive([], ['US'])->eligible)->toBeFalse()
        ->and($exclusive([Region::NorthAmerica])->eligible)->toBeFalse();
});
