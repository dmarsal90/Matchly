<?php

declare(strict_types=1);

use App\Modules\Jobs\Domain\Enums\CloseReason;
use App\Modules\Jobs\Domain\Enums\JobStatus;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Jobs\Infrastructure\Queue\CheckJobLinksJob;
use App\Modules\Shared\Infrastructure\Http\PublicUrlGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    // Test hosts resolve to a public documentation address unless they say "internal".
    $this->app->instance(PublicUrlGuard::class, new PublicUrlGuard(
        fn (string $host): array => str_contains($host, 'internal') ? ['10.0.0.5'] : ['93.184.216.34'],
    ));
});

it('closes jobs older than the freshness window', function (): void {
    $old = JobListing::factory()->postedDaysAgo(31)->create();
    $fresh = JobListing::factory()->postedDaysAgo(29)->create();

    $this->artisan('jobs:close-expired')->expectsOutputToContain('Closed 1 expired jobs')->assertSuccessful();

    expect($old->refresh()->status)->toBe(JobStatus::Closed)
        ->and($old->close_reason)->toBe(CloseReason::Expired)
        ->and($fresh->refresh()->status)->toBe(JobStatus::Open);
});

it('hides closed, duplicate and stale jobs from listings, newest first', function (): void {
    $newest = JobListing::factory()->postedDaysAgo(1)->create();
    $older = JobListing::factory()->postedDaysAgo(5)->create();
    JobListing::factory()->closed()->create();
    JobListing::factory()->postedDaysAgo(40)->create();
    JobListing::factory()->create(['duplicate_of_id' => $newest->id]);

    expect(JobListing::query()->visible()->latestFirst()->pluck('id')->all())->toBe([$newest->id, $older->id]);
});

it('falls back to first sighting when the source gives no date', function (): void {
    $undated = JobListing::factory()->create(['posted_at' => null, 'first_seen_at' => now()->subDays(2)]);

    expect($undated->refresh()->sort_at->toDateString())->toBe(now()->subDays(2)->toDateString());
});

it('closes jobs whose apply link is gone', function (): void {
    Http::preventStrayRequests();
    $dead = JobListing::factory()->create(['apply_url' => 'https://gone.test/jobs/1']);
    $alive = JobListing::factory()->create(['apply_url' => 'https://alive.test/jobs/2']);
    $flaky = JobListing::factory()->create(['apply_url' => 'https://flaky.test/jobs/3']);
    Http::fake([
        'gone.test/*' => Http::response('', 404),
        'alive.test/*' => Http::response('ok'),
        'flaky.test/*' => fn () => throw new ConnectionException('timeout'),
    ]);

    $this->artisan('jobs:check-links --sync')->expectsOutputToContain('Closed 1 of 3')->assertSuccessful();

    expect($dead->refresh()->close_reason)->toBe(CloseReason::LinkDead)
        ->and($alive->refresh()->status)->toBe(JobStatus::Open)
        ->and($alive->link_checked_at)->not->toBeNull()
        ->and($flaky->refresh()->status)->toBe(JobStatus::Open);
});

it('confirms with GET when a server rejects HEAD', function (): void {
    $job = JobListing::factory()->create(['apply_url' => 'https://nohead.test/jobs/1']);
    Http::fake(['nohead.test/*' => fn ($request) => $request->method() === 'HEAD' ? Http::response('', 405) : Http::response('ok')]);

    $this->artisan('jobs:check-links --sync')->assertSuccessful();

    expect($job->refresh()->status)->toBe(JobStatus::Open);
});

it('never requests links that resolve to private addresses', function (): void {
    Http::preventStrayRequests();
    Http::fake();
    $job = JobListing::factory()->create(['apply_url' => 'https://jobs.internal-corp.test/1']);

    $this->artisan('jobs:check-links --sync')->assertSuccessful();

    Http::assertNothingSent();
    expect($job->refresh()->status)->toBe(JobStatus::Open);
});

it('validates every redirect hop before following it', function (): void {
    Http::preventStrayRequests();
    $job = JobListing::factory()->create(['apply_url' => 'https://redirector.test/jobs/1']);
    Http::fake([
        'redirector.test/*' => Http::response('', 302, ['Location' => 'http://metadata.internal-cloud.test/latest']),
        '*' => Http::response('should not be called', 200),
    ]);

    $this->artisan('jobs:check-links --sync')->assertSuccessful();

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'internal'));
    expect($job->refresh()->status)->toBe(JobStatus::Open);
});

it('follows safe redirects to detect closed ATS postings', function (): void {
    $job = JobListing::factory()->create(['apply_url' => 'https://boards.greenhouse.io/acme/jobs/1']);
    Http::fake([
        'boards.greenhouse.io/acme/jobs/1' => Http::response('', 301, ['Location' => '/acme?error=true']),
        'boards.greenhouse.io/acme?error=true' => Http::response('board'),
    ]);

    $this->artisan('jobs:check-links --sync')->assertSuccessful();

    expect($job->refresh()->close_reason)->toBe(CloseReason::LinkDead);
});

it('queues link checks in batches for jobs not checked recently', function (): void {
    Queue::fake();
    config(['jobs.link_check.batch_size' => 2]);
    JobListing::factory()->count(3)->create();
    JobListing::factory()->create(['link_checked_at' => now()]);

    $this->artisan('jobs:check-links')->expectsOutputToContain('Queued 3 link checks')->assertSuccessful();

    Queue::assertPushed(CheckJobLinksJob::class, 2);
    Queue::assertPushed(CheckJobLinksJob::class, fn (CheckJobLinksJob $job): bool => $job->queue === 'maintenance');
});

it('schedules ingestion and maintenance', function (): void {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('jobs:ingest')
        ->expectsOutputToContain('jobs:close-expired')
        ->expectsOutputToContain('jobs:check-links')
        ->assertSuccessful();
});
