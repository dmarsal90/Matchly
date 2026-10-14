<?php

declare(strict_types=1);

use App\Modules\Jobs\Domain\Enums\RemoteScope;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Providers\Application\Actions\IngestProvider;
use App\Modules\Providers\Infrastructure\Models\AtsCompany;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    config(['jobs.http.retries' => 1]);
    foreach (array_keys(config('jobs.providers')) as $key) {
        config(["jobs.providers.{$key}.rate_limit" => 1000]);
    }
});

/*
 * Every source against its real (trimmed) API response. "now" is set per source
 * so the fixtures' own publication dates fall inside the freshness window.
 */
it('ingests every source end to end', function (string $source, string $now, Closure $arrange, int $expected, ?Closure $assert = null): void {
    $this->travelTo($now);
    $arrange();

    $report = app(IngestProvider::class)->handle($source);

    expect($report->createdIds)->toHaveCount($expected)
        ->and(JobListing::query()->count())->toBe($expected);

    $assert?->__invoke(JobListing::query()->with('source')->orderBy('id')->get());
})->with([
    'remotive' => ['remotive', '2026-10-14 13:00:00', fn () => Http::fake(['remotive.com/*' => Http::response(providerFixtureRaw('remotive.json'))]), 3,
        function ($jobs): void {
            expect($jobs->first()->remote_scope)->toBe(RemoteScope::Worldwide)
                ->and($jobs->first()->source->name)->toBe('Remotive')
                ->and($jobs->first()->salary_annual_min)->toBe('35300.00');
        }],
    'remoteok' => ['remoteok', '2026-10-14 13:00:00', fn () => Http::fake(['remoteok.com/*' => Http::response(providerFixtureRaw('remoteok.json'))]), 3, null],
    'jobicy' => ['jobicy', '2026-10-14 13:00:00', fn () => Http::fake(['jobicy.com/*' => Http::response(providerFixtureRaw('jobicy.json'))]), 3,
        function ($jobs): void {
            expect($jobs->first()->allowed_regions)->toBe(['CA', 'US'])
                ->and($jobs->first()->remote_scope)->toBe(RemoteScope::CountryRestricted);
        }],
    'himalayas' => ['himalayas', '2026-10-14 13:00:00', fn () => Http::fake(['himalayas.app/*' => Http::response(providerFixtureRaw('himalayas.json'))]), 3, null],
    'arbeitnow (on-site postings dropped)' => ['arbeitnow', '2026-10-14 13:00:00', fn () => Http::fake(['www.arbeitnow.com/*' => Http::response(providerFixtureRaw('arbeitnow.json'))]), 2, null],
    'we work remotely (deduplicated across feeds)' => ['weworkremotely', '2026-10-14 13:00:00', function (): void {
        config(['jobs.providers.weworkremotely.options.feeds' => ['https://weworkremotely.com/a.rss', 'https://weworkremotely.com/b.rss']]);
        Http::fake(['weworkremotely.com/*' => Http::response(providerFixtureRaw('weworkremotely.rss'), 200, ['Content-Type' => 'application/rss+xml'])]);
    }, 3, function ($jobs): void {
        expect($jobs->pluck('company')->all())->toContain('Tailscale');
    }],
    'greenhouse (stale postings skipped)' => ['greenhouse', '2026-10-14 13:00:00', function (): void {
        AtsCompany::factory()->platform('greenhouse')->create(['identifier' => 'gitlab', 'name' => 'GitLab']);
        Http::fake(['boards-api.greenhouse.io/v1/boards/gitlab/*' => Http::response(providerFixtureRaw('greenhouse.json'))]);
    }, 1, function ($jobs): void {
        expect($jobs->first()->external_id)->toBe('gitlab:8860302002')
            ->and($jobs->first()->allowed_regions)->toBe(['FR'])
            ->and($jobs->first()->description)->not->toContain('&lt;');
    }],
    'lever' => ['lever', '2026-08-20 12:00:00', function (): void {
        AtsCompany::factory()->platform('lever')->create(['identifier' => 'palantir', 'name' => 'Palantir']);
        Http::fake(['api.lever.co/v0/postings/palantir*' => Http::response(providerFixtureRaw('lever.json'))]);
    }, 1, function ($jobs): void {
        expect($jobs->first()->work_mode)->toBe(WorkMode::Hybrid)->and($jobs->first()->company)->toBe('Palantir');
    }],
    'ashby' => ['ashby', '2022-02-01 12:00:00', function (): void {
        AtsCompany::factory()->platform('ashby')->create(['identifier' => 'linear', 'name' => 'Linear']);
        Http::fake(['api.ashbyhq.com/posting-api/job-board/linear*' => Http::response(providerFixtureRaw('ashby.json'))]);
    }, 1, function ($jobs): void {
        expect($jobs->first()->remote_scope)->toBe(RemoteScope::Region)->and($jobs->first()->allowed_regions)->toBe(['EU']);
    }],
    'workable' => ['workable', '2026-09-10 12:00:00', function (): void {
        AtsCompany::factory()->platform('workable')->create(['identifier' => 'huggingface', 'name' => 'Hugging Face']);
        Http::fake(['apply.workable.com/api/v1/widget/accounts/huggingface*' => Http::response(providerFixtureRaw('workable.json'))]);
    }, 1, null],
    'smartrecruiters (details only for remote postings)' => ['smartrecruiters', '2026-10-14 13:00:00', function (): void {
        AtsCompany::factory()->platform('smartrecruiters')->create(['identifier' => 'ServiceNow', 'name' => 'ServiceNow']);
        $list = providerFixture('smartrecruiters_list.json');
        $list['content'][0]['location']['remote'] = true;
        $detail = providerFixture('smartrecruiters_detail.json');
        $detail['location']['remote'] = true;
        Http::fake([
            'api.smartrecruiters.com/v1/companies/ServiceNow/postings/*' => Http::response($detail),
            'api.smartrecruiters.com/v1/companies/ServiceNow/postings*' => Http::response($list),
        ]);
    }, 1, function (): void {
        Http::assertSentCount(2);
    }],
    'adzuna' => ['adzuna', '2026-10-14 13:00:00', function (): void {
        config(['jobs.providers.adzuna.options' => [...config('jobs.providers.adzuna.options'), 'app_id' => 'id', 'app_key' => 'key', 'countries' => ['gb'], 'max_pages' => 1]]);
        Http::fake(['api.adzuna.com/*' => Http::response(['results' => [[
            'id' => '1', 'title' => 'Remote Laravel Developer', 'description' => 'Join us', 'created' => '2026-10-13T10:00:00Z',
            'redirect_url' => 'https://www.adzuna.co.uk/jobs/land/ad/1', 'company' => ['display_name' => 'Acme'],
            'location' => ['display_name' => 'UK', 'area' => ['UK']], 'category' => ['label' => 'IT Jobs'],
        ]]])]);
    }, 1, function ($jobs): void {
        expect($jobs->first()->allowed_regions)->toBe(['GB']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'app_id=id') && str_contains($request->url(), 'what=remote'));
    }],
    'jooble' => ['jooble', '2026-10-14 13:00:00', function (): void {
        config(['jobs.providers.jooble.options' => [...config('jobs.providers.jooble.options'), 'api_key' => 'secret', 'max_pages' => 1]]);
        Http::fake(['jooble.org/api/*' => Http::response(['totalCount' => 1, 'jobs' => [[
            'id' => 7, 'title' => 'Remote Support Agent', 'location' => 'Spain', 'snippet' => 'Help customers', 'link' => 'https://jooble.org/desc/7',
            'company' => 'Acme', 'updated' => '2026-10-13T00:00:00.0000000', 'type' => 'Full-time', 'salary' => '',
        ]]])]);
    }, 1, function (): void {
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['keywords'] === 'remote');
    }],
]);
