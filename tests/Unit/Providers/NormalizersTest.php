<?php

declare(strict_types=1);

use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\RemoteScope;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Sources\Adzuna\AdzunaNormalizer;
use App\Modules\Providers\Infrastructure\Sources\Arbeitnow\ArbeitnowNormalizer;
use App\Modules\Providers\Infrastructure\Sources\Ats\Ashby\AshbyNormalizer;
use App\Modules\Providers\Infrastructure\Sources\Ats\Greenhouse\GreenhouseNormalizer;
use App\Modules\Providers\Infrastructure\Sources\Ats\Lever\LeverNormalizer;
use App\Modules\Providers\Infrastructure\Sources\Ats\SmartRecruiters\SmartRecruitersNormalizer;
use App\Modules\Providers\Infrastructure\Sources\Ats\Workable\WorkableNormalizer;
use App\Modules\Providers\Infrastructure\Sources\Himalayas\HimalayasNormalizer;
use App\Modules\Providers\Infrastructure\Sources\Jobicy\JobicyNormalizer;
use App\Modules\Providers\Infrastructure\Sources\Jooble\JoobleNormalizer;
use App\Modules\Providers\Infrastructure\Sources\JSearch\JSearchNormalizer;
use App\Modules\Providers\Infrastructure\Sources\RemoteOk\RemoteOkNormalizer;
use App\Modules\Providers\Infrastructure\Sources\Remotive\RemotiveNormalizer;
use App\Modules\Providers\Infrastructure\Sources\WeWorkRemotely\WeWorkRemotelyNormalizer;

const COMPANY = ['identifier' => 'acme', 'name' => 'Acme Corp'];

it('normalizes Remotive', function (): void {
    $job = providerFixture('remotive.json')['jobs'][0];
    $draft = (new RemotiveNormalizer)->normalize(new RawJob('remotive', (string) $job['id'], $job));

    expect($draft)->not->toBeNull()
        ->and($draft->title)->toBe('Remote Office Assistant')
        ->and($draft->company)->toBe('Coalition Technologies')
        ->and($draft->applyUrl)->toBe($job['url'])
        ->and($draft->sourceUrl)->toBe($job['url'])
        ->and($draft->locationText)->toBe('Worldwide')
        ->and($draft->employmentType)->toBe(EmploymentType::FullTime)
        ->and($draft->salary?->min)->toBe(35300.0)
        ->and($draft->salary?->max)->toBe(52000.0)
        ->and($draft->salaryText)->toBe('$35,3k- $52k')
        ->and($draft->tags)->toContain('php', 'shopify')
        ->and($draft->postedAt?->format('Y-m-d H:i'))->toBe('2026-10-11 20:17')
        ->and($draft->remoteByDefault)->toBeTrue();
});

it('normalizes Remote OK and skips the legal notice', function (): void {
    $items = providerFixture('remoteok.json');
    $job = $items[1];
    $draft = (new RemoteOkNormalizer)->normalize(new RawJob('remoteok', (string) $job['id'], $job));

    expect($items[0])->toHaveKey('legal')
        ->and($draft?->title)->toBe('Principal Product Manager Strategic Initiatives')
        ->and($draft?->company)->toBe('Thyme Care')
        ->and($draft?->applyUrl)->toStartWith('https://remoteOK.com/remote-jobs/')
        ->and($draft?->salary)->toBeNull()
        ->and($draft?->postedAt?->format('Y-m-d'))->toBe('2026-10-13');
});

it('normalizes Jobicy with explicit salary and level', function (): void {
    $job = providerFixture('jobicy.json')['jobs'][0];
    $draft = (new JobicyNormalizer)->normalize(new RawJob('jobicy', (string) $job['id'], $job));

    expect($draft?->title)->toBe('Director of Enterprise Sales')
        ->and($draft?->locationText)->toBe('Canada,  USA')
        ->and($draft?->seniority)->toBe(Seniority::Director)
        ->and($draft?->salary?->min)->toBe(213500.0)
        ->and($draft?->salary?->currency)->toBe('USD')
        ->and($draft?->salary?->period)->toBe(SalaryPeriod::Year);
});

it('normalizes Himalayas with explicit location restrictions', function (): void {
    $job = providerFixture('himalayas.json')['jobs'][0];
    $draft = (new HimalayasNormalizer)->normalize(new RawJob('himalayas', $job['guid'], $job));

    expect($draft?->company)->toBe('Camping World')
        ->and($draft?->workMode)->toBe(WorkMode::Remote)
        ->and($draft?->allowedRegions)->toBe(['United States'])
        ->and($draft?->remoteScope)->toBeNull()
        ->and($draft?->salary?->period)->toBe(SalaryPeriod::Hour)
        ->and($draft?->salary?->min)->toBe(18.45);
});

it('treats Himalayas jobs without restrictions as worldwide', function (): void {
    $job = [...providerFixture('himalayas.json')['jobs'][0], 'locationRestrictions' => []];
    $draft = (new HimalayasNormalizer)->normalize(new RawJob('himalayas', $job['guid'], $job));

    expect($draft?->remoteScope)->toBe(RemoteScope::Worldwide);
});

it('keeps only remote Arbeitnow postings', function (): void {
    [$remote, , $onsite] = providerFixture('arbeitnow.json')['data'];
    $normalizer = new ArbeitnowNormalizer;

    expect($normalizer->normalize(new RawJob('arbeitnow', $remote['slug'], $remote))?->workMode)->toBe(WorkMode::Remote)
        ->and($normalizer->normalize(new RawJob('arbeitnow', $onsite['slug'], $onsite)))->toBeNull();
});

it('normalizes We Work Remotely RSS items', function (): void {
    $draft = (new WeWorkRemotelyNormalizer)->normalize(new RawJob('weworkremotely', 'guid', [
        'title' => 'Tailscale: Brand Designer',
        'region' => 'Anywhere in the World',
        'country' => '',
        'category' => 'Sales and Marketing',
        'type' => 'Full-Time',
        'skills' => 'Figma, Branding',
        'description' => '<p>Design our brand.</p>',
        'pubDate' => 'Wed, 14 Oct 2026 07:31:00 +0000',
        'link' => 'https://weworkremotely.com/remote-jobs/tailscale-brand-designer-1',
    ]));

    expect($draft?->company)->toBe('Tailscale')
        ->and($draft?->title)->toBe('Brand Designer')
        ->and($draft?->locationText)->toBe('Anywhere in the World')
        ->and($draft?->tags)->toBe(['figma', 'branding'])
        ->and($draft?->postedAt?->format('Y-m-d H:i'))->toBe('2026-10-14 07:31');
});

it('normalizes Greenhouse and decodes its entity-encoded HTML', function (): void {
    $job = providerFixture('greenhouse.json')['jobs'][0];
    $draft = (new GreenhouseNormalizer)->normalize(new RawJob('greenhouse', "gitlab:{$job['id']}", [...$job, '_company' => COMPANY]));

    expect($draft?->title)->toBe('Account Executive - France')
        ->and($draft?->company)->toBe('GitLab')
        ->and($draft?->description)->toStartWith('<div class="content-intro">')
        ->and($draft?->locationText)->toContain('Remote, France')
        ->and($draft?->categoryHint)->toBe('EMEA - Commercial');
});

it('normalizes Lever including workplace type and lists', function (): void {
    $job = providerFixture('lever.json')[0];
    $draft = (new LeverNormalizer)->normalize(new RawJob('lever', "acme:{$job['id']}", [...$job, '_company' => COMPANY]));

    expect($draft?->company)->toBe('Acme Corp')
        ->and($draft?->workMode)->toBe(WorkMode::Hybrid)
        ->and($draft?->applyUrl)->toEndWith('/apply')
        ->and($draft?->description)->toContain('<h4>What We Value</h4>')
        ->and($draft?->employmentType)->toBe(EmploymentType::FullTime)
        ->and($draft?->postedAt?->format('Y'))->toBe('2026');
});

it('normalizes Ashby', function (): void {
    $job = providerFixture('ashby.json')['jobs'][0];
    $draft = (new AshbyNormalizer)->normalize(new RawJob('ashby', "linear:{$job['id']}", [...$job, '_company' => COMPANY]));

    expect($draft?->title)->toBe('Senior / Staff Fullstack Engineer')
        ->and($draft?->workMode)->toBe(WorkMode::Remote)
        ->and($draft?->locationText)->toBe('Europe | European Union')
        ->and($draft?->employmentType)->toBe(EmploymentType::FullTime)
        ->and($draft?->applyUrl)->toEndWith('/application');
});

it('reads Ashby compensation components', function (): void {
    $job = [...providerFixture('ashby.json')['jobs'][0], 'compensation' => ['summaryComponents' => [
        ['compensationType' => 'EquityPercentage', 'minValue' => 0.1],
        ['compensationType' => 'Salary', 'interval' => '1 YEAR', 'currencyCode' => 'EUR', 'minValue' => 90000, 'maxValue' => 120000],
    ]]];
    $draft = (new AshbyNormalizer)->normalize(new RawJob('ashby', 'x', [...$job, '_company' => COMPANY]));

    expect($draft?->salary?->min)->toBe(90000.0)->and($draft?->salary?->currency)->toBe('EUR');
});

it('normalizes Workable', function (): void {
    $job = providerFixture('workable.json')['jobs'][0];
    $draft = (new WorkableNormalizer)->normalize(new RawJob('workable', "huggingface:{$job['shortcode']}", [...$job, '_company' => COMPANY]));

    expect($draft?->workMode)->toBe(WorkMode::Remote)
        ->and($draft?->locationText)->toBe('Paris, France')
        ->and($draft?->applyUrl)->toEndWith('/apply')
        ->and($draft?->postedAt?->format('Y-m-d'))->toBe('2026-05-29');
});

it('normalizes SmartRecruiters details into one description', function (): void {
    $job = providerFixture('smartrecruiters_detail.json');
    $job['location']['remote'] = true;
    $draft = (new SmartRecruitersNormalizer)->normalize(new RawJob('smartrecruiters', "x:{$job['id']}", [...$job, '_company' => COMPANY]));

    expect($draft?->title)->toBe('Sales Director, Retail')
        ->and($draft?->company)->toBe('ServiceNow')
        ->and($draft?->workMode)->toBe(WorkMode::Remote)
        ->and($draft?->seniority)->toBe(Seniority::Director)
        ->and($draft?->description)->toContain('<h4>Company Description</h4>');
});

it('normalizes Adzuna search results and ignores predicted salaries', function (): void {
    $job = [
        'id' => '4321', 'title' => '<strong>Remote</strong> PHP Developer', 'description' => 'Join our remote team...',
        'created' => '2026-10-12T09:00:00Z', 'redirect_url' => 'https://www.adzuna.co.uk/jobs/land/ad/4321',
        'company' => ['display_name' => 'Acme Ltd'], 'location' => ['display_name' => 'London', 'area' => ['UK', 'London']],
        'category' => ['label' => 'IT Jobs'], 'salary_min' => 50000, 'salary_max' => 65000, 'salary_is_predicted' => '0',
        'contract_time' => 'full_time', '_country' => 'gb',
    ];
    $normalizer = new AdzunaNormalizer;
    $draft = $normalizer->normalize(new RawJob('adzuna', 'gb:4321', $job));
    $predicted = $normalizer->normalize(new RawJob('adzuna', 'gb:4321', [...$job, 'salary_is_predicted' => '1']));

    expect($draft?->title)->toBe('Remote PHP Developer')
        ->and($draft?->salary?->currency)->toBe('GBP')
        ->and($draft?->locationText)->toBe('London, UK')
        ->and($draft?->employmentType)->toBe(EmploymentType::FullTime)
        ->and($predicted?->salary)->toBeNull();
});

it('normalizes Jooble results', function (): void {
    $draft = (new JoobleNormalizer)->normalize(new RawJob('jooble', '99', [
        'id' => 99, 'title' => 'Remote Customer Support Agent', 'location' => 'Spain', 'snippet' => 'Help our <b>customers</b>...',
        'salary' => '€1.800 - €2.200 per month', 'type' => 'Full-time', 'link' => 'https://jooble.org/desc/99',
        'company' => 'Acme', 'updated' => '2026-10-13T00:00:00.0000000',
    ]));

    expect($draft?->description)->toBe('<p>Help our customers...</p>')
        ->and($draft?->salary?->period)->toBe(SalaryPeriod::Month)
        ->and($draft?->salary?->min)->toBe(1800.0)
        ->and($draft?->postedAt?->format('Y-m-d'))->toBe('2026-10-13');
});

it('normalizes JSearch results', function (): void {
    $draft = (new JSearchNormalizer)->normalize(new RawJob('jsearch', 'abc', [
        'job_id' => 'abc', 'job_title' => 'Remote Software Developer', 'employer_name' => 'Turing', 'job_employment_type' => 'Full-time',
        'job_apply_link' => 'https://www.monster.com/job-openings/1', 'job_description' => "Line one\nLine two", 'job_is_remote' => true,
        'job_location' => 'Anywhere', 'job_posted_at_datetime_utc' => '2026-10-28T00:00:00.000Z',
        'job_min_salary' => 200, 'job_max_salary' => 300, 'job_salary_period' => 'HOUR',
    ]));

    expect($draft?->title)->toBe('Remote Software Developer')
        ->and($draft?->locationText)->toBe('Anywhere')
        ->and($draft?->employmentType)->toBe(EmploymentType::FullTime)
        ->and($draft?->salary?->period)->toBe(SalaryPeriod::Hour)
        ->and($draft?->remoteByDefault)->toBeTrue()
        ->and($draft?->postedAt?->format('Y-m-d'))->toBe('2026-10-28');
});

it('skips payloads missing required fields', function (): void {
    expect((new RemotiveNormalizer)->normalize(new RawJob('remotive', '1', ['title' => 'Only a title'])))->toBeNull()
        ->and((new WeWorkRemotelyNormalizer)->normalize(new RawJob('weworkremotely', '1', ['title' => 'No company separator', 'link' => 'https://x.test'])))->toBeNull();
});
