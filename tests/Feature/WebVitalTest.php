<?php

declare(strict_types=1);

it('accepts a valid beacon without a CSRF token', function (): void {
    $this->postJson('/vitals', ['metric' => 'LCP', 'page' => 'jobs/Index', 'value' => 1830.4])->assertNoContent();
});

it('rejects unknown metrics, odd page names and out-of-range values', function (array $payload): void {
    $this->postJson('/vitals', $payload)->assertUnprocessable();
})->with([
    'metric' => [['metric' => 'FID', 'page' => 'jobs/Index', 'value' => 10]],
    'page' => [['metric' => 'LCP', 'page' => '/shared/abc?x=1', 'value' => 10]],
    'negative' => [['metric' => 'LCP', 'page' => 'jobs/Index', 'value' => -1]],
    'huge' => [['metric' => 'LCP', 'page' => 'jobs/Index', 'value' => 999999]],
]);

it('never touches the session, so a concurrent beacon cannot eat flash data', function (): void {
    $this->withSession(['flash_probe' => 'kept'])
        ->postJson('/vitals', ['metric' => 'LCP', 'page' => 'jobs/Index', 'value' => 10])
        ->assertNoContent()
        ->assertCookieMissing(config('session.cookie'));
});

it('answers 422 to an invalid beacon sent without a JSON Accept header', function (): void {
    $this->call('POST', '/vitals', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"metric":"FID"}')->assertUnprocessable();
});
