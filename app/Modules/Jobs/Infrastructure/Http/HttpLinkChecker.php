<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Infrastructure\Http;

use App\Modules\Jobs\Domain\Contracts\LinkChecker;
use App\Modules\Shared\Domain\Exceptions\UnsafeUrl;
use App\Modules\Shared\Infrastructure\Http\PublicUrlGuard;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * Apply URLs come from third-party feeds, so every hop is SSRF-checked:
 * the host must resolve to public addresses only, the connection is pinned
 * to the validated IP and redirects are followed by hand.
 */
final readonly class HttpLinkChecker implements LinkChecker
{
    private const int MAX_REDIRECTS = 5;

    /** Final URLs ATSs redirect to when a posting no longer exists. */
    private const array CLOSED_URL_PATTERNS = [
        '/[?&]error=true\b/',           // Greenhouse
        '#jobs\.lever\.co/[^/]+/?$#',   // Lever sends removed postings to the company board
        '#/404\b#',
        '#job-not-found|posting-not-found|position-filled#i',
    ];

    public function __construct(
        private Http $http,
        private RateLimiter $limiter,
        private Config $config,
        private PublicUrlGuard $guard,
    ) {}

    public function isDead(string $url): bool
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $limiterKey = "link-check:{$host}";

        // Over the per-host budget: report alive and let the next round check it.
        if ($this->limiter->tooManyAttempts($limiterKey, (int) $this->config->get('jobs.link_check.per_host_per_minute', 30))) {
            return false;
        }
        $this->limiter->hit($limiterKey, 60);

        try {
            [$response, $finalUrl] = $this->follow('head', $url);

            // Many servers reject HEAD; confirm with a real GET before deciding.
            if (in_array($response->status(), [403, 404, 405, 410, 501], true)) {
                [$response, $finalUrl] = $this->follow('get', $url);
            }
        } catch (UnsafeUrl) {
            // Never request it; an admin can review the job. Not "dead" either.
            return false;
        } catch (Throwable) {
            return false;
        }

        if (in_array($response->status(), [404, 410], true)) {
            return true;
        }

        if ($finalUrl === $url || ! $response->successful()) {
            return false;
        }

        foreach (self::CLOSED_URL_PATTERNS as $pattern) {
            if (preg_match($pattern, $finalUrl) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: Response, 1: string} final response and URL
     */
    private function follow(string $method, string $url): array
    {
        $current = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $target = $this->guard->resolve($current);
            $response = $this->request($method, $current, $target);

            $location = $response->header('Location');
            if (! $response->redirect() || $location === '') {
                return [$response, $current];
            }

            $current = (string) UriResolver::resolve(Utils::uriFor($current), Utils::uriFor($location));
        }

        throw new RuntimeException("Too many redirects for {$url}");
    }

    /**
     * @param  array{host: string, port: int, ip: string}  $target
     */
    private function request(string $method, string $url, array $target): Response
    {
        $ip = str_contains($target['ip'], ':') ? "[{$target['ip']}]" : $target['ip'];

        return $this->http
            ->withUserAgent(sprintf('%s/1.0 (+%s)', str_replace(' ', '', (string) $this->config->get('app.name')), (string) $this->config->get('app.url')))
            ->timeout(15)
            ->connectTimeout(5)
            ->withOptions([
                'allow_redirects' => false,
                // Pin DNS to the address that passed the guard.
                'curl' => [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$ip}"]],
            ])
            ->send(strtoupper($method), $url);
    }
}
