<?php

declare(strict_types=1);

use App\Modules\Providers\Infrastructure\Models\Source;
use App\Modules\Providers\Infrastructure\Queue\IngestProviderJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    config(['jobs.providers.adzuna.enabled' => false, 'jobs.providers.jooble.enabled' => false]);
});

it('queues one job per enabled source', function (): void {
    $this->artisan('jobs:ingest')->assertSuccessful();

    Queue::assertPushed(IngestProviderJob::class, 11);
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
