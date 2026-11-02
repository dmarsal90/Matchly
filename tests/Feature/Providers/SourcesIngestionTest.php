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
    'workingnomads' => ['workingnomads', '2026-10-20 13:00:00', fn () => Http::fake(['www.workingnomads.com/*' => Http::response(providerFixtureRaw('workingnomads.json'))]), 3, null],
    'puentetalent' => ['puentetalent', '2026-10-16 13:00:00', fn () => Http::fake(['puentetalent.com/*' => Http::response(providerFixtureRaw('puentetalent.json'))]), 3,
        function ($jobs): void {
            expect($jobs->first()->allowed_regions)->toContain('MX');
        }],
    'getonboard' => ['getonboard', '2026-08-29 13:00:00', fn () => Http::fake(['www.getonbrd.com/*' => Http::response(providerFixtureRaw('getonboard.json'))]), 3, function ($jobs): void {
        expect($jobs->first()->apply_url)->toStartWith('https://www.getonbrd.com/jobs/');
    }],
    'remotelanders' => ['remotelanders', '2026-10-20 13:00:00', fn () => Http::fake(['remotelanders.com/*' => Http::response(providerFixtureRaw('remotelanders.json'))]), 3, null],
    'remotejobsorg' => ['remotejobsorg', '2026-10-20 13:00:00', fn () => Http::fake(['remotejobs.org/*' => Http::response(providerFixtureRaw('remotejobsorg.json'))]), 3, null],
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
    'jsearch' => ['jsearch', '2026-10-30 13:00:00', function (): void {
        config(['jobs.providers.jsearch.options' => [...config('jobs.providers.jsearch.options'), 'api_key' => 'secret']]);
        Http::fake(['api.openwebninja.com/*' => Http::response(['data' => ['jobs' => [[
            'job_id' => 'j1', 'job_title' => 'Remote Laravel Developer', 'employer_name' => 'Acme', 'job_apply_link' => 'https://example.com/apply/1',
            'job_description' => 'Join us', 'job_is_remote' => true, 'job_location' => 'Anywhere', 'job_posted_at_datetime_utc' => '2026-10-29T10:00:00.000Z',
        ]]]])]);
    }, 1, function (): void {
        Http::assertSent(fn ($request) => $request->hasHeader('X-API-Key', 'secret') && str_contains($request->url(), 'work_from_home=true'));
    }],
    'googlejobs' => ['googlejobs', '2026-10-30 13:00:00', function (): void {
        config(['jobs.providers.googlejobs.options' => [...config('jobs.providers.googlejobs.options'), 'api_key' => 'secret']]);
        Http::fake(['api.openwebninja.com/*' => Http::response(['data' => [[
            'job_id' => 'g1', 'job_title' => 'Remote Laravel Developer', 'employer_name' => 'Acme', 'job_apply_link' => 'https://example.com/apply/1',
            'job_description' => 'Join us', 'job_is_remote' => true, 'job_location' => 'Anywhere', 'job_posted_at_datetime_utc' => '2026-10-29T10:00:00.000Z',
        ]]])]);
    }, 1, function (): void {
        Http::assertSent(fn ($request) => str_contains($request->url(), 'google-jobs/search') && $request->hasHeader('X-API-Key', 'secret'));
    }],
    'linkedin' => ['linkedin', '2026-10-30 13:00:00', function (): void {
        config(['jobs.providers.linkedin.options' => [...config('jobs.providers.linkedin.options'), 'api_key' => 'secret']]);
        Http::fake(['api.openwebninja.com/*' => Http::response(['data' => [[
            'job_id' => 'l1', 'job_title' => 'Remote Laravel Developer', 'employer_name' => 'Acme', 'job_apply_link' => 'https://www.linkedin.com/jobs/view/1',
            'job_description' => 'Join us, 100% remote', 'job_is_remote' => true, 'job_location' => 'Anywhere', 'job_posted_at_datetime_utc' => '2026-10-29T10:00:00.000Z',
        ]]])]);
    }, 1, function (): void {
        Http::assertSent(fn ($request) => str_contains($request->url(), 'realtime-linkedin-data/search') && $request->hasHeader('X-API-Key', 'secret'));
    }],
    'bingjobs' => ['bingjobs', '2026-10-30 13:00:00', function (): void {
        config(['jobs.providers.bingjobs.options' => [...config('jobs.providers.bingjobs.options'), 'api_key' => 'secret']]);
        Http::fake(['api.openwebninja.com/*' => Http::response(['data' => [[
            'job_id' => 'b1', 'job_title' => 'Remote Laravel Developer', 'employer_name' => 'Acme', 'job_apply_link' => 'https://example.com/apply/1',
            'job_description' => 'Join us', 'job_is_remote' => true, 'job_location' => 'Anywhere', 'job_posted_at_datetime_utc' => '2026-10-29T10:00:00.000Z',
        ]]])]);
    }, 1, function (): void {
        Http::assertSent(fn ($request) => str_contains($request->url(), 'realtime-bing-jobs-data/search') && $request->hasHeader('X-API-Key', 'secret'));
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
    'themuse' => ['themuse', '2026-10-30 13:00:00', function (): void {
        Http::fake(['themuse.com/*' => Http::response(['page' => 1, 'page_count' => 1, 'results' => [[
            'id' => 22302737, 'name' => 'Staff Software Engineer, Backend', 'contents' => '<p>Build things</p>', 'publication_date' => '2026-10-29T19:14:51Z',
            'locations' => [['name' => 'Flexible / Remote']], 'categories' => [['name' => 'Software Engineering']], 'levels' => [['name' => 'Senior Level', 'short_name' => 'senior']],
            'refs' => ['landing_page' => 'https://www.themuse.com/jobs/celonis/staff-engineer'], 'company' => ['name' => 'Celonis'],
        ]]])]);
    }, 1, function ($jobs): void {
        expect($jobs->first()->company)->toBe('Celonis');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'location=Flexible'));
    }],
    'arbetsformedlingen' => ['arbetsformedlingen', '2026-10-30 13:00:00', function (): void {
        Http::fake(['jobsearch.api.jobtechdev.se/*' => Http::response(['hits' => [[
            'id' => '31666659', 'headline' => 'Arbeta remote som mötesbokare', 'webpage_url' => 'https://arbetsformedlingen.se/platsbanken/annonser/31666659',
            'publication_date' => '2026-10-28T08:00:00', 'description' => ['text' => 'Om jobbet', 'text_formatted' => '<p>Om jobbet</p>'],
            'employer' => ['name' => 'P94 Group'], 'working_hours_type' => ['label' => 'Heltid'], 'occupation_field' => ['label' => 'Försäljning'],
            'workplace_address' => ['municipality' => 'Stockholm', 'country' => 'Sverige'], 'application_details' => ['url' => 'https://p94group.se/karriar'],
        ]]])]);
    }, 1, function ($jobs): void {
        expect($jobs->first()->allowed_regions)->toBe(['SE'])
            ->and($jobs->first()->apply_url)->toBe('https://p94group.se/karriar');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'remote=true'));
    }],
    'reed' => ['reed', '2026-10-30 13:00:00', function (): void {
        config(['jobs.providers.reed.options' => [...config('jobs.providers.reed.options'), 'api_key' => 'secret', 'max_pages' => 1]]);
        Http::fake(['reed.co.uk/*' => Http::response(['results' => [[
            'jobId' => 55, 'jobTitle' => 'Remote PHP Developer', 'employerName' => 'Acme', 'locationName' => 'London', 'jobDescription' => 'Build APIs',
            'minimumSalary' => 50000, 'maximumSalary' => 65000, 'currency' => 'GBP', 'date' => '28/10/2026', 'jobUrl' => 'https://www.reed.co.uk/jobs/remote-php/55',
        ]]])]);
    }, 1, function ($jobs): void {
        expect($jobs->first()->allowed_regions)->toBe(['GB'])
            ->and($jobs->first()->salary_annual_min)->toBe('50000.00');
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('secret:')));
    }],
    'usajobs' => ['usajobs', '2026-10-30 13:00:00', function (): void {
        config(['jobs.providers.usajobs.options' => [...config('jobs.providers.usajobs.options'), 'api_key' => 'k', 'email' => 'me@example.com', 'max_pages' => 1]]);
        Http::fake(['data.usajobs.gov/*' => Http::response(['SearchResult' => ['SearchResultItems' => [[
            'MatchedObjectId' => '800123', 'MatchedObjectDescriptor' => [
                'PositionID' => 'IT-1', 'PositionTitle' => 'IT Specialist (Remote)', 'PositionURI' => 'https://www.usajobs.gov/job/800123',
                'ApplyURI' => ['https://www.usajobs.gov/job/800123/apply'], 'OrganizationName' => 'Department of Energy', 'PositionLocationDisplay' => 'Anywhere in the U.S.',
                'PositionRemuneration' => [['MinimumRange' => '92108', 'MaximumRange' => '119746', 'RateIntervalCode' => 'PA']],
                'PublicationStartDate' => '2026-10-27T00:00:00Z', 'UserArea' => ['Details' => ['JobSummary' => 'Support systems']],
            ],
        ]]]])]);
    }, 1, function ($jobs): void {
        expect($jobs->first()->allowed_regions)->toBe(['US'])
            ->and($jobs->first()->salary_annual_max)->toBe('119746.00');
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization-Key', 'k') && $request->hasHeader('User-Agent', 'me@example.com') && str_contains($request->url(), 'RemoteIndicator=True'));
    }],
    'francetravail' => ['francetravail', '2026-10-30 13:00:00', function (): void {
        config(['jobs.providers.francetravail.options' => [...config('jobs.providers.francetravail.options'), 'client_id' => 'id', 'client_secret' => 'secret', 'max_pages' => 1]]);
        Http::fake([
            'entreprise.francetravail.fr/*' => Http::response(['access_token' => 'tok', 'expires_in' => 1499]),
            'api.francetravail.io/*' => Http::response(['resultats' => [[
                'id' => '195XYZ', 'intitule' => 'Développeur PHP en télétravail', 'description' => 'Missions...', 'dateCreation' => '2026-10-28T10:00:00.000Z',
                'lieuTravail' => ['libelle' => '75 - Paris'], 'entreprise' => ['nom' => 'Société Acme'], 'typeContrat' => 'CDI',
                'origineOffre' => ['urlOrigine' => 'https://candidat.francetravail.fr/offres/recherche/detail/195XYZ'],
            ]]]),
        ]);
    }, 1, function ($jobs): void {
        expect($jobs->first()->allowed_regions)->toBe(['FR']);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer tok'));
    }],
]);

it('treats an empty France Travail answer (204) as no offers', function (): void {
    $this->travelTo('2026-10-30 13:00:00');
    config(['jobs.providers.francetravail.options' => [...config('jobs.providers.francetravail.options'), 'client_id' => 'id', 'client_secret' => 'secret']]);
    Http::fake([
        'entreprise.francetravail.fr/*' => Http::response(['access_token' => 'tok']),
        'api.francetravail.io/*' => Http::response('', 204),
    ]);

    expect(app(IngestProvider::class)->handle('francetravail')->createdIds)->toBeEmpty();
});
