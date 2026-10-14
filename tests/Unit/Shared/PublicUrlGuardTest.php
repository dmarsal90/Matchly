<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\Exceptions\UnsafeUrl;
use App\Modules\Shared\Infrastructure\Http\PublicUrlGuard;

function guardResolvingTo(string ...$ips): PublicUrlGuard
{
    return new PublicUrlGuard(fn (string $host): array => array_values($ips));
}

it('accepts public http(s) URLs and returns the pinned address', function (): void {
    expect(guardResolvingTo('93.184.216.34')->resolve('https://jobs.example.com/apply?id=1'))
        ->toBe(['host' => 'jobs.example.com', 'port' => 443, 'ip' => '93.184.216.34']);
});

it('rejects URLs that are internal without resolving them', function (string $url): void {
    expect(guardResolvingTo('93.184.216.34')->looksPublic($url))->toBeFalse();
})->with([
    'loopback literal' => ['http://127.0.0.1/admin'],
    'cloud metadata' => ['http://169.254.169.254/latest/meta-data'],
    'private literal' => ['http://192.168.1.10/'],
    'ipv6 loopback' => ['http://[::1]/'],
    'ipv4-mapped ipv6' => ['http://[::ffff:10.0.0.1]/'],
    'docker service name' => ['http://redis:6379/'],
    'localhost' => ['http://localhost/'],
    'internal suffix' => ['https://vault.corp.internal/'],
    'other scheme' => ['file:///etc/passwd'],
    'gopher' => ['gopher://example.com/'],
    'credentials' => ['https://user:pass@example.com/'],
    'unusual port' => ['https://example.com:6379/'],
    'not absolute' => ['/relative/path'],
]);

it('rejects hosts that resolve to any non-public address', function (array $ips): void {
    expect(fn () => guardResolvingTo(...$ips)->resolve('https://evil.example.com/'))->toThrow(UnsafeUrl::class);
})->with([
    'private' => [['10.0.0.5']],
    'one bad among good (rebinding-style)' => [['93.184.216.34', '127.0.0.1']],
    'cgnat' => [['100.64.0.1']],
    'ipv6 unique local' => [['fd00::1']],
    'unresolvable' => [[]],
]);
