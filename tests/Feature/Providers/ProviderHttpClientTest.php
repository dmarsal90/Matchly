<?php

declare(strict_types=1);

use App\Modules\Providers\Domain\Exceptions\ProviderRequestFailed;
use App\Modules\Providers\Infrastructure\Http\ProviderHttpClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    config(['jobs.http.retry_base_ms' => 1, 'jobs.providers.remotive.rate_limit' => 1000, 'jobs.providers.remotive.cache_ttl' => 600]);
});

it('retries transient errors with back-off', function (): void {
    Http::fake(['api.test/*' => Http::sequence()->push('busy', 503)->push('slow down', 429)->push(['ok' => true])]);

    expect(app(ProviderHttpClient::class)->getJson('remotive', 'https://api.test/jobs'))->toBe(['ok' => true]);
    Http::assertSentCount(3);
});

it('does not retry client errors', function (): void {
    Http::fake(['api.test/*' => Http::response('nope', 404)]);

    expect(fn () => app(ProviderHttpClient::class)->getJson('remotive', 'https://api.test/jobs'))
        ->toThrow(ProviderRequestFailed::class, 'HTTP 404');
    Http::assertSentCount(1);
});

it('reuses cached responses within the source cache TTL', function (): void {
    Http::fake(['api.test/*' => Http::response(['n' => 1])]);
    $client = app(ProviderHttpClient::class);

    $client->getJson('remotive', 'https://api.test/jobs', ['page' => 1]);
    $client->getJson('remotive', 'https://api.test/jobs', ['page' => 1]);
    $client->getJson('remotive', 'https://api.test/jobs', ['page' => 2]);

    Http::assertSentCount(2);
});

it('revalidates with ETag and reuses the body on 304', function (): void {
    config(['jobs.providers.remotive.cache_ttl' => 0]);
    Http::fake(['api.test/*' => Http::sequence()
        ->push(['jobs' => [1, 2]], 200, ['ETag' => '"v1"'])
        ->push('', 304, ['ETag' => '"v1"'])]);
    $client = app(ProviderHttpClient::class);

    $client->getJson('remotive', 'https://api.test/jobs');
    expect($client->getJson('remotive', 'https://api.test/jobs'))->toBe(['jobs' => [1, 2]]);

    Http::assertSent(fn (Request $request): bool => $request->header('If-None-Match') === ['"v1"']);
});

it('identifies itself with the configured product name', function (): void {
    Http::fake(['api.test/*' => Http::response([])]);

    app(ProviderHttpClient::class)->getJson('remotive', 'https://api.test/jobs');

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->header('User-Agent')[0], str_replace(' ', '', config('app.name')).'/1.0'));
});

it('fails instead of waiting forever when the per-minute budget is exhausted', function (): void {
    config(['jobs.providers.remotive.rate_limit' => 1, 'jobs.providers.remotive.cache_ttl' => 0, 'jobs.http.max_throttle_wait_seconds' => 0]);
    Http::fake(['api.test/*' => Http::response([])]);
    $client = app(ProviderHttpClient::class);

    $client->getJson('remotive', 'https://api.test/a');

    expect(fn () => $client->getJson('remotive', 'https://api.test/b'))->toThrow(ProviderRequestFailed::class, 'HTTP 429');
});

it('rejects responses that are not JSON', function (): void {
    Http::fake(['api.test/*' => Http::response('<html>maintenance</html>')]);

    expect(fn () => app(ProviderHttpClient::class)->getJson('remotive', 'https://api.test/jobs'))->toThrow(ProviderRequestFailed::class);
});
