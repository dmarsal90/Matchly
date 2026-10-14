<?php

declare(strict_types=1);

use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Enums\JobCategory;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\RemoteScope;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Jobs\Domain\Services\TaxonomyClassifier;
use App\Modules\Jobs\Infrastructure\Classifiers\RegionDictionary;
use App\Modules\Jobs\Infrastructure\Classifiers\RuleBasedWorkModeClassifier;

function makeDraft(array $overrides = []): JobDraft
{
    return new JobDraft(...[
        'source' => 'test',
        'externalId' => '1',
        'title' => 'Backend Engineer',
        'company' => 'Acme',
        'description' => '<p>Work on our API.</p>',
        'applyUrl' => 'https://acme.test/jobs/1',
        ...$overrides,
    ]);
}

function makeClassifier(): RuleBasedWorkModeClassifier
{
    return new RuleBasedWorkModeClassifier(new RegionDictionary, new TaxonomyClassifier);
}

describe('RegionDictionary', function (): void {
    it('extracts countries and regions from free-text locations', function (string $text, array $expected): void {
        expect((new RegionDictionary)->extract($text))->toBe($expected);
    })->with([
        'country list' => ['Canada,  USA', ['CA', 'US']],
        'remote prefix' => ['Remote, France', ['FR']],
        'US state' => ['Sterling Heights, Michigan', ['US']],
        'US state code' => ['Austin, TX', ['US']],
        'Canadian province' => ['Toronto, ON', ['CA']],
        'uppercase code' => ['Remote - UK only', ['GB']],
        'region' => ['EMEA', ['EMEA']],
        'country does not leak region' => ['South Africa', ['ZA']],
        'latin america is a region, not the US' => ['Latin America', ['LATAM']],
        'european union over europe' => ['Europe | European Union', ['EU']],
        'spanish and german names' => ['España o Deutschland', ['ES', 'DE']],
        'lowercase words are not codes' => ['remote in any city', []],
    ]);

    it('recognizes worldwide wording', function (string $text, bool $expected): void {
        expect((new RegionDictionary)->isWorldwide($text))->toBe($expected);
    })->with([
        ['Worldwide', true],
        ['Anywhere in the World', true],
        ['Remote (global)', true],
        ['Remote', false],
        ['United States', false],
    ]);

    it('resolves explicit values from sources', function (): void {
        $dictionary = new RegionDictionary;

        expect($dictionary->resolve('United States'))->toBe('US')
            ->and($dictionary->resolve('uk'))->toBe('GB')
            ->and($dictionary->resolve('de'))->toBe('DE')
            ->and($dictionary->resolve('Atlantis'))->toBeNull();
    });
});

describe('RuleBasedWorkModeClassifier', function (): void {
    it('decides work mode from location and title decorations', function (array $draft, WorkMode $expected): void {
        expect(makeClassifier()->classify(makeDraft($draft))->workMode)->toBe($expected);
    })->with([
        'remote location' => [['locationText' => 'Remote, France'], WorkMode::Remote],
        'hybrid location' => [['locationText' => 'Berlin (Hybrid)'], WorkMode::Hybrid],
        'title decoration' => [['title' => 'Designer (Remote)', 'locationText' => 'Berlin'], WorkMode::Remote],
        'dash decoration' => [['title' => 'Data Analyst - Hybrid', 'locationText' => 'Madrid'], WorkMode::Hybrid],
        'hybrid inside the role name is not hybrid work' => [['title' => 'Hybrid Cloud Engineer', 'locationText' => 'Madrid'], WorkMode::Onsite],
        'remote-only board default' => [['locationText' => 'Kansas', 'remoteByDefault' => true], WorkMode::Remote],
        'explicit value wins' => [['locationText' => 'Remote', 'workMode' => WorkMode::Hybrid], WorkMode::Hybrid],
        'nothing said on a mixed source' => [['locationText' => 'Paris, France'], WorkMode::Onsite],
    ]);

    it('decides remote scope and allowed regions', function (array $draft, ?RemoteScope $scope, array $regions): void {
        $classification = makeClassifier()->classify(makeDraft(['remoteByDefault' => true, ...$draft]));

        expect($classification->remoteScope)->toBe($scope)
            ->and($classification->allowedRegions)->toBe($regions);
    })->with([
        'worldwide' => [['locationText' => 'Anywhere in the World'], RemoteScope::Worldwide, []],
        'countries' => [['locationText' => 'Canada, USA'], RemoteScope::CountryRestricted, ['CA', 'US']],
        'region' => [['locationText' => 'Europe'], RemoteScope::Region, ['EUROPE']],
        'EU is a region, not a country' => [['locationText' => 'European Union'], RemoteScope::Region, ['EU']],
        'region from the title' => [['title' => 'ML Engineer - EMEA Remote', 'locationText' => 'Paris, France'], RemoteScope::Region, ['FR', 'EMEA']],
        'explicit list from the source' => [['locationText' => null, 'allowedRegions' => ['United States', 'de']], RemoteScope::CountryRestricted, ['US', 'DE']],
        'unknown is not worldwide' => [['locationText' => 'Remote'], null, []],
    ]);

    it('leaves scope empty for on-site jobs', function (): void {
        expect(makeClassifier()->classify(makeDraft(['locationText' => 'On-site, Madrid, Spain']))->remoteScope)->toBeNull();
    });

    it('infers role, category and seniority from the title', function (string $title, ?string $hint, ?JobRole $role, ?JobCategory $category): void {
        $classification = makeClassifier()->classify(makeDraft(['title' => $title, 'categoryHint' => $hint, 'remoteByDefault' => true]));

        expect($classification->role)->toBe($role)->and($classification->category)->toBe($category);
    })->with([
        ['Senior Frontend Developer (Vue)', null, JobRole::Frontend, JobCategory::SoftwareDevelopment],
        ['Full Stack Engineer', null, JobRole::Fullstack, JobCategory::SoftwareDevelopment],
        ['Senior Laravel Developer', null, JobRole::Backend, JobCategory::SoftwareDevelopment],
        ['iOS Engineer', null, JobRole::Mobile, JobCategory::SoftwareDevelopment],
        ['Site Reliability Engineer', null, JobRole::DevOps, JobCategory::DevOps],
        ['Machine Learning Engineer', null, JobRole::MachineLearning, JobCategory::Data],
        ['Product Designer', null, JobRole::Designer, JobCategory::Design],
        ['Mobile Marketing Operations Manager', null, JobRole::Marketing, JobCategory::Marketing],
        ['Tier III Service Desk Engineer', null, JobRole::CustomerSupport, JobCategory::CustomerSupport],
        ['Account Executive - France', null, JobRole::Sales, JobCategory::Sales],
        ['CFD Engineer', 'Engineering', null, JobCategory::SoftwareDevelopment],
        ['Admissions Advisor', 'Education', null, JobCategory::Education],
        ['Admissions Advisor', null, null, null],
    ]);
});
