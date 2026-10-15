<?php

declare(strict_types=1);

use App\Modules\Jobs\Domain\Enums\Region;
use App\Modules\Jobs\Domain\Enums\RemoteScope;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Matching\Domain\Data\JobRequirements;
use App\Modules\Matching\Domain\Data\JobSnapshot;
use App\Modules\Matching\Domain\Services\RegionMembership;
use App\Modules\Matching\Domain\Services\RuleBasedRequirementExtractor;
use App\Modules\Profiles\Domain\Enums\LanguageLevel;

function extractRequirements(string $text, array $overrides = []): JobRequirements
{
    return (new RuleBasedRequirementExtractor(catalog()))->extract(new JobSnapshot(...[
        'id' => 1,
        'title' => 'Senior Laravel Developer',
        'text' => $text,
        ...$overrides,
    ]));
}

const POSTING = <<<'TXT'
We are looking for a backend engineer to join our team.

Requirements:
• 5+ years of professional experience building web applications
• 3+ years of PHP and Laravel
• Solid PostgreSQL and Redis knowledge
• Fluent English (C1); Spanish is a plus
• Bachelor's degree in Computer Science or equivalent experience

Nice to have: Vue.js, Docker, Kubernetes.

Please include a cover letter and a link to your GitHub. Tell us your salary expectations.
You must be based in the EU. Working hours overlap with CET.
TXT;

it('separates required and nice-to-have skills', function (): void {
    $requirements = extractRequirements(POSTING);

    expect(array_column($requirements->requiredSkills, 'skill'))->toBe(['laravel', 'php', 'postgresql', 'redis'])
        ->and(array_column($requirements->niceToHaveSkills, 'skill'))->toBe(['vue', 'docker', 'kubernetes'])
        ->and($requirements->source)->toBe(JobRequirements::SOURCE_RULES);
});

it('reads overall and per-skill years', function (): void {
    $requirements = extractRequirements(POSTING);
    $years = array_column($requirements->requiredSkills, 'min_years', 'skill');

    expect($requirements->minYearsExperience)->toBe(5)
        ->and($years)->toMatchArray(['php' => 3.0, 'laravel' => 3.0, 'postgresql' => null]);
});

it('reads languages with levels and whether they are required', function (): void {
    // "Spanish is a plus" says nothing about speaking it at a level: not a requirement.
    expect(extractRequirements(POSTING)->languages)->toBe([
        ['code' => 'en', 'level' => LanguageLevel::C1, 'required' => true],
    ]);

    expect(extractRequirements("Requirements:\nFluent English.\nSpanish would be a plus.")->languages)->toBe([
        ['code' => 'en', 'level' => LanguageLevel::C1, 'required' => true],
    ]);
});

it('does not require a degree when equivalent experience is accepted', function (): void {
    expect(extractRequirements(POSTING)->degreeRequired)->toBeFalse()
        ->and(extractRequirements("Bachelor's degree in Computer Science required.")->degreeRequired)->toBeTrue()
        ->and(extractRequirements("Bachelor's degree in Computer Science required.")->degreeField)->toBe('Computer Science');
});

it('detects what the application asks for', function (): void {
    expect(extractRequirements(POSTING)->asksFor)->toBe([
        'cover_letter' => true,
        'github' => true,
        'portfolio' => false,
        'salary_expectations' => true,
        'additional_questions' => false,
    ]);
});

it('marks location restrictions as exclusive and reads time zones', function (): void {
    $requirements = extractRequirements(POSTING, ['allowedRegions' => ['EU']]);

    expect($requirements->locationExclusive)->toBeTrue()
        ->and($requirements->regions)->toBe([Region::EuropeanUnion])
        ->and([$requirements->utcOffsetMin, $requirements->utcOffsetMax])->toBe([1, 1])
        ->and(extractRequirements('Work between UTC-5 and UTC+1.')->utcOffsetMin)->toBe(-5)
        ->and(extractRequirements('Remote, US only', ['remoteScope' => RemoteScope::CountryRestricted, 'allowedRegions' => ['US']])->countries)->toBe(['US']);
});

it('keeps the classified seniority and detects the posting language', function (): void {
    expect(extractRequirements(POSTING, ['seniority' => Seniority::Senior])->seniority)->toBe(Seniority::Senior)
        ->and(extractRequirements(POSTING)->language)->toBe('en')
        ->and(extractRequirements('Buscamos una persona con experiencia en Laravel para el equipo de la empresa, que trabaje con nosotros.')->language)->toBe('es');
});

it('uses provider tags as nice-to-have hints only', function (): void {
    $requirements = extractRequirements('We build things.', ['title' => 'Engineer', 'tags' => ['react', 'remote', 'Node.js']]);

    expect($requirements->requiredSkills)->toBe([])
        ->and(array_column($requirements->niceToHaveSkills, 'skill'))->toBe(['react', 'nodejs']);
});

it('round-trips through its stored form', function (): void {
    $requirements = extractRequirements(POSTING, ['allowedRegions' => ['EU', 'GB']]);

    expect(JobRequirements::fromArray($requirements->toArray()))->toEqual($requirements);
});

it('knows which regions a country belongs to', function (): void {
    expect(RegionMembership::regionsOf('es'))->toBe([Region::EuropeanUnion, Region::Europe, Region::Emea])
        ->and(RegionMembership::regionsOf('MX'))->toBe([Region::NorthAmerica, Region::LatinAmerica, Region::Americas])
        ->and(RegionMembership::contains(Region::Apac, 'AU'))->toBeTrue()
        ->and(RegionMembership::contains(Region::EuropeanUnion, 'GB'))->toBeFalse();
});
