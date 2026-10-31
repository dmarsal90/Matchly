<?php

declare(strict_types=1);

use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Providers\Application\MonthlyQuota;
use App\Modules\Providers\Application\ProviderRegistry;
use App\Modules\Providers\Infrastructure\Models\Source;
use App\Modules\Providers\Infrastructure\Queue\IngestProviderJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    config(['jobs.providers.adzuna.enabled' => false, 'jobs.providers.jooble.enabled' => false, 'jobs.providers.jsearch.enabled' => false, 'jobs.providers.googlejobs.enabled' => false]);
});

it('queues one job per enabled source', function (): void {
    $this->artisan('jobs:ingest')->assertSuccessful();

    Queue::assertPushed(IngestProviderJob::class, 16);
    Queue::assertPushed(IngestProviderJob::class, fn (IngestProviderJob $job): bool => $job->source === 'remotive' && $job->queue === 'ingestion');
    Queue::assertNotPushed(IngestProviderJob::class, fn (IngestProviderJob $job): bool => $job->source === 'adzuna');
});

it('respects each source interval unless forced', function (): void {
    Source::query()->create(['key' => 'remotive', 'name' => 'Remotive', 'homepage_url' => 'https://remotive.com', 'last_fetched_at' => now()->subHour()]);

    $this->artisan('jobs:ingest')->expectsOutputToContain('not due until')->assertSuccessful();
    Queue::assertNotPushed(IngestProviderJob::class, fn (IngestProviderJob $job): bool => $job->source === 'remotive');

    $this->artisan('jobs:ingest --force')->assertSuccessful();
    Queue::assertPushed(IngestProviderJob::class, fn (IngestProviderJob $job): bool => $job->source === 'remotive');
});

it('skips sources an admin disabled', function (): void {
    Source::query()->create(['key' => 'remoteok', 'name' => 'Remote OK', 'homepage_url' => 'https://remoteok.com', 'enabled' => false]);

    $this->artisan('jobs:ingest remoteok')->expectsOutputToContain('disabled by an admin')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('rejects unknown sources', function (): void {
    $this->artisan('jobs:ingest nope')->assertExitCode(2);
});

it('can run a source synchronously', function (): void {
    $this->travelTo('2026-10-14 13:00:00');
    config(['jobs.providers.remotive.rate_limit' => 1000]);
    Http::fake(['remotive.com/*' => Http::response(providerFixtureRaw('remotive.json'))]);

    $this->artisan('jobs:ingest remotive --sync')->expectsOutputToContain('3 fetched · 3 new')->assertSuccessful();
});

it('makes ingestion jobs unique per source', function (): void {
    $job = new IngestProviderJob('remotive');

    expect($job->uniqueId())->toBe('remotive')
        ->and($job->backoff())->toBe([60, 300, 900])
        ->and($job->middleware())->toHaveCount(1);
});

it('lists sources with their status', function (): void {
    $this->artisan('jobs:sources')->expectsOutputToContain('remotive')->assertSuccessful();
});

it('ingests its source when the queued job runs and logs a summary', function (): void {
    $this->travelTo('2026-10-14 13:00:00');
    config(['jobs.providers.remotive.rate_limit' => 1000]);
    Http::fake(['remotive.com/*' => Http::response(providerFixtureRaw('remotive.json'))]);
    Log::spy();

    app()->call([new IngestProviderJob('remotive'), 'handle']);

    expect(JobListing::query()->count())->toBe(3);
    Log::shouldHaveReceived('info')->with('Job source ingested', Mockery::on(
        fn (array $context): bool => $context['source'] === 'remotive' && $context['fetched'] === 3 && $context['created'] === 3,
    ));
});

it('logs the error when every retry failed', function (): void {
    Log::spy();

    (new IngestProviderJob('remotive'))->failed(new RuntimeException('upstream down'));

    Log::shouldHaveReceived('error')->with('Job source ingestion failed', ['source' => 'remotive', 'error' => 'upstream down']);
});

it('drops a source from the run once its monthly quota is used up', function (): void {
    config(['jobs.providers.jsearch.enabled' => true, 'jobs.providers.jsearch.monthly_limit' => 2]);
    $quota = app(MonthlyQuota::class);
    $registry = app(ProviderRegistry::class);

    $quota->hit('jsearch');
    expect($registry->enabled())->toContain('jsearch');

    $quota->hit('jsearch');
    expect($registry->enabled())->not->toContain('jsearch');
});

it('resets the quota on the provider reset date window', function (): void {
    config(['jobs.providers.googlejobs.quota_resets_on' => '2026-12-01', 'jobs.providers.googlejobs.quota_days' => 31]);
    $quota = app(MonthlyQuota::class);

    $this->travelTo('2026-11-15');
    $quota->hit('googlejobs');
    expect($quota->used('googlejobs'))->toBe(1);

    $this->travelTo('2026-11-30');
    expect($quota->used('googlejobs'))->toBe(1);

    $this->travelTo('2026-12-01');
    expect($quota->used('googlejobs'))->toBe(0);
});
