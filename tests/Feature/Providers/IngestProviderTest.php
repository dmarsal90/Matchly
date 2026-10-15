<?php

declare(strict_types=1);

use App\Modules\Jobs\Application\Events\JobsIngested;
use App\Modules\Jobs\Domain\Enums\CloseReason;
use App\Modules\Jobs\Domain\Enums\JobStatus;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Providers\Application\Actions\IngestProvider;
use App\Modules\Providers\Domain\Contracts\IngestionLog;
use App\Modules\Providers\Domain\Exceptions\ProviderRequestFailed;
use App\Modules\Providers\Infrastructure\Models\AtsCompany;
use App\Modules\Providers\Infrastructure\Models\IngestionRun;
use App\Modules\Providers\Infrastructure\Models\Source;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/** Mutable upstream responses: Http::fake() cannot replace a stub once registered. */
final class Upstream
{
    /** @var array<string, mixed> */
    public static array $responses = [];
}

beforeEach(function (): void {
    Upstream::$responses = ['remotive' => ['jobs' => []]];
    Http::preventStrayRequests();
    Http::fake([
        'remotive.com/*' => fn () => is_int(Upstream::$responses['remotive'])
            ? Http::response('down', Upstream::$responses['remotive'])
            : Http::response(Upstream::$responses['remotive']),
        'boards-api.greenhouse.io/v1/boards/*' => function ($request) {
            preg_match('#/boards/([^/]+)/#', $request->url(), $match);
            $response = Upstream::$responses['greenhouse:'.$match[1]] ?? ['jobs' => []];

            return is_int($response) ? Http::response('error', $response) : Http::response($response);
        },
    ]);
    $this->travelTo('2026-10-14 13:00:00');
    config(['jobs.http.retries' => 1, 'jobs.providers.remotive.rate_limit' => 1000, 'jobs.providers.greenhouse.rate_limit' => 1000]);
});

/**
 * @param  list<array<string, mixed>>  $jobs
 */
function fakeRemotive(array $jobs): void
{
    // Responses are cached per source for cache_ttl; each simulated run must hit "the API".
    Cache::flush();
    Upstream::$responses['remotive'] = ['jobs' => $jobs];
}

/**
 * @param  array<string, mixed>|int  $response  board payload or an HTTP error status
 */
function fakeGreenhouse(string $company, array|int $response): void
{
    Cache::flush();
    Upstream::$responses["greenhouse:{$company}"] = $response;
}

/**
 * @return array<string, mixed>
 */
function remotiveJob(array $overrides = []): array
{
    return [
        'id' => 1,
        'url' => 'https://remotive.com/remote-jobs/software-dev/backend-engineer-1',
        'title' => 'Backend Engineer',
        'company_name' => 'Acme',
        'category' => 'Software Development',
        'tags' => ['php'],
        'job_type' => 'full_time',
        'publication_date' => '2026-10-13T10:00:00',
        'candidate_required_location' => 'Worldwide',
        'salary' => '',
        'description' => '<p>We are hiring a backend engineer to build and scale our public APIs and internal tooling.</p>',
        ...$overrides,
    ];
}

it('stores jobs, records the run and announces new jobs', function (): void {
    Event::fake([JobsIngested::class]);
    fakeRemotive([remotiveJob(), remotiveJob(['id' => 2, 'url' => 'https://remotive.com/remote-jobs/x-2', 'title' => 'Designer'])]);

    $report = app(IngestProvider::class)->handle('remotive');

    expect($report->fetched)->toBe(2)->and($report->createdIds)->toHaveCount(2);
    Event::assertDispatched(JobsIngested::class, fn (JobsIngested $event): bool => $event->source === 'remotive' && count($event->jobIds) === 2);

    $run = IngestionRun::query()->sole();
    expect($run->status)->toBe(IngestionRun::SUCCEEDED)
        ->and($run->created)->toBe(2)
        ->and(Source::query()->where('key', 'remotive')->value('last_succeeded_at'))->not->toBeNull();
});

it('is idempotent: a second identical run creates nothing and announces nothing', function (): void {
    fakeRemotive([remotiveJob()]);
    app(IngestProvider::class)->handle('remotive');

    Event::fake([JobsIngested::class]);
    fakeRemotive([remotiveJob()]);
    $report = app(IngestProvider::class)->handle('remotive');

    expect($report->createdIds)->toBe([])
        ->and($report->unchanged)->toBe(1)
        ->and(JobListing::query()->count())->toBe(1);
    Event::assertNotDispatched(JobsIngested::class);
});

it('resets derived data when a posting changes', function (): void {
    fakeRemotive([remotiveJob()]);
    app(IngestProvider::class)->handle('remotive');
    JobListing::query()->update(['requirements' => json_encode(['skills' => ['php']]), 'requirements_extracted_at' => now()]);

    Event::fake([JobsIngested::class]);
    fakeRemotive([remotiveJob(['description' => '<p>We now also need strong Go experience to build and scale our public APIs.</p>'])]);
    $report = app(IngestProvider::class)->handle('remotive');

    $job = JobListing::query()->sole();
    expect($report->updated)->toBe(1)
        ->and($job->requirements)->toBeNull()
        ->and($job->requirements_extracted_at)->toBeNull();
    Event::assertDispatched(JobsIngested::class, fn (JobsIngested $event): bool => $event->changedJobIds === [$job->id]);
});

it('closes postings the source stopped listing and reopens them if they come back', function (): void {
    fakeRemotive([remotiveJob(), remotiveJob(['id' => 2, 'url' => 'https://remotive.com/remote-jobs/x-2', 'title' => 'Designer'])]);
    app(IngestProvider::class)->handle('remotive');

    $this->travel(1)->hours();
    fakeRemotive([remotiveJob()]);
    $report = app(IngestProvider::class)->handle('remotive');

    $gone = JobListing::query()->where('external_id', '2')->sole();
    expect($report->closed)->toBe(1)
        ->and($gone->status)->toBe(JobStatus::Closed)
        ->and($gone->close_reason)->toBe(CloseReason::RemovedFromSource);

    $this->travel(1)->hours();
    fakeRemotive([remotiveJob(), remotiveJob(['id' => 2, 'url' => 'https://remotive.com/remote-jobs/x-2', 'title' => 'Designer'])]);
    app(IngestProvider::class)->handle('remotive');

    expect($gone->refresh()->status)->toBe(JobStatus::Open)->and($gone->closed_at)->toBeNull();
});

it('never closes anything after an empty response', function (): void {
    fakeRemotive([remotiveJob()]);
    app(IngestProvider::class)->handle('remotive');

    $this->travel(1)->hours();
    fakeRemotive([]);
    app(IngestProvider::class)->handle('remotive');

    expect(JobListing::query()->sole()->status)->toBe(JobStatus::Open);
});

it('never closes jobs after a partial failure', function (): void {
    AtsCompany::factory()->platform('greenhouse')->create(['identifier' => 'gitlab', 'name' => 'GitLab']);
    AtsCompany::factory()->platform('greenhouse')->create(['identifier' => 'down', 'name' => 'Down Inc']);
    fakeGreenhouse('gitlab', providerFixture('greenhouse.json'));
    app(IngestProvider::class)->handle('greenhouse');
    expect(JobListing::query()->where('status', JobStatus::Open)->count())->toBe(1);

    $this->travel(1)->hours();
    fakeGreenhouse('gitlab', 500);
    $report = app(IngestProvider::class)->handle('greenhouse');

    expect($report->isPartial())->toBeTrue()
        ->and($report->closed)->toBe(0)
        ->and(JobListing::query()->where('status', JobStatus::Open)->count())->toBe(1)
        ->and(IngestionRun::query()->latest('id')->value('status'))->toBe(IngestionRun::PARTIAL)
        ->and(AtsCompany::query()->where('identifier', 'gitlab')->value('last_error'))->toContain('500');
});

it('marks the same posting from a second source as a duplicate', function (): void {
    AtsCompany::factory()->platform('greenhouse')->create(['identifier' => 'acme', 'name' => 'Acme']);
    fakeGreenhouse('acme', ['jobs' => [[
        'id' => 55, 'title' => 'Backend Engineer (Remote)', 'company_name' => 'Acme Inc.',
        'absolute_url' => 'https://job-boards.greenhouse.io/acme/jobs/55',
        'location' => ['name' => 'Remote'], 'content' => e('<p>Build APIs for our customers all over the world, with a great team.</p>'),
        'first_published' => '2026-10-12T10:00:00Z',
    ]]]);
    app(IngestProvider::class)->handle('greenhouse');

    Event::fake([JobsIngested::class]);
    fakeRemotive([remotiveJob()]);
    $report = app(IngestProvider::class)->handle('remotive');

    $copy = JobListing::query()->where('external_id', '1')->sole();
    expect($report->duplicates)->toBe(1)
        ->and($report->createdIds)->toBe([])
        ->and($copy->duplicate_of_id)->toBe(JobListing::query()->where('external_id', 'acme:55')->value('id'));
    Event::assertNotDispatched(JobsIngested::class);
});

it('holds suspicious postings back for moderation', function (): void {
    fakeRemotive([remotiveJob(['description' => '<p>Great pay! Contact our recruiter on Telegram and pay the registration fee to start earning today.</p>'])]);

    $report = app(IngestProvider::class)->handle('remotive');

    $job = JobListing::query()->sole();
    expect($report->flagged)->toBe(1)
        ->and($report->createdIds)->toBe([])
        ->and($job->status)->toBe(JobStatus::Flagged)
        ->and($job->flag_reasons)->toBe(['off_platform_contact', 'upfront_payment']);
});

it('sanitizes descriptions before storing them', function (): void {
    fakeRemotive([remotiveJob(['description' => '<h1>Join us</h1><p onclick="steal()">Build APIs and scale our platform for millions of users.</p><script>alert(1)</script><a href="javascript:x()">x</a><a href="https://acme.test">site</a>'])]);

    app(IngestProvider::class)->handle('remotive');

    $html = JobListing::query()->sole()->description;
    expect($html)->not->toContain('<script', 'onclick', 'javascript:', '<h1')
        ->toContain('<h3>Join us</h3>')
        ->toContain('rel="nofollow noreferrer noopener"');
});

it('skips postings outside the freshness window, on-site ones and broken URLs', function (): void {
    fakeRemotive([
        remotiveJob(['id' => 1, 'publication_date' => '2026-08-01T00:00:00']),
        remotiveJob(['id' => 2, 'url' => 'not-a-url']),
        remotiveJob(['id' => 3, 'url' => 'https://remotive.com/remote-jobs/3', 'candidate_required_location' => 'On-site, Madrid']),
        remotiveJob(['id' => 4, 'url' => 'https://remotive.com/remote-jobs/4']),
    ]);

    $report = app(IngestProvider::class)->handle('remotive');

    expect($report->skipped)->toBe(3)
        ->and($report->skipReasons)->toBe(['stale' => 1, 'invalid_url' => 1, 'work_mode' => 1])
        // jsonb does not keep key order (PostgreSQL).
        ->and(IngestionRun::query()->sole()->skip_reasons)->toEqual(['stale' => 1, 'invalid_url' => 1, 'work_mode' => 1])
        ->and(JobListing::query()->pluck('external_id')->all())->toBe(['4']);
});

it('stores a posting listed twice in the same run once', function (): void {
    fakeRemotive([remotiveJob(), remotiveJob(['title' => 'Backend Engineer II'])]);

    $report = app(IngestProvider::class)->handle('remotive');

    expect($report->createdIds)->toHaveCount(1)->and(JobListing::query()->sole()->title)->toBe('Backend Engineer II');
});

it('does not flag companies that merely mention messaging platforms', function (): void {
    fakeRemotive([remotiveJob(['description' => '<p>Build our WhatsApp and Telegram messaging channels used by millions of customers worldwide.</p>'])]);

    app(IngestProvider::class)->handle('remotive');

    expect(JobListing::query()->sole()->status)->toBe(JobStatus::Open);
});

it('clamps publication dates in the future', function (): void {
    fakeRemotive([remotiveJob(['publication_date' => '2027-01-01T00:00:00'])]);

    app(IngestProvider::class)->handle('remotive');

    expect(JobListing::query()->sole()->posted_at?->toDateTimeString())->toBe('2026-10-14 13:00:00');
});

it('records failures and backs off exponentially', function (): void {
    Upstream::$responses['remotive'] = 503;

    expect(fn () => app(IngestProvider::class)->handle('remotive'))->toThrow(ProviderRequestFailed::class, 'HTTP 503');
    expect(fn () => app(IngestProvider::class)->handle('remotive'))->toThrow(ProviderRequestFailed::class, 'HTTP 503');

    $source = Source::query()->where('key', 'remotive')->sole();
    expect($source->consecutive_failures)->toBe(2)
        ->and(IngestionRun::query()->where('status', IngestionRun::FAILED)->count())->toBe(2)
        // 360 min interval × 2² after two failures.
        ->and(app(IngestionLog::class)->nextFetchAt('remotive', 360)?->format('Y-m-d H:i'))->toBe('2026-10-15 13:00');
});
