<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Infrastructure\Http;

use App\Modules\Jobs\Domain\Contracts\LinkChecker;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Response;
use Throwable;

final readonly class HttpLinkChecker implements LinkChecker
{
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
            $response = $this->request('head', $url);

            // Many servers reject HEAD; confirm with a real GET before deciding.
            if (in_array($response->status(), [403, 404, 405, 410, 501], true)) {
                $response = $this->request('get', $url);
            }
        } catch (Throwable) {
            return false;
        }

        if (in_array($response->status(), [404, 410], true)) {
            return true;
        }

        $finalUrl = (string) ($response->effectiveUri() ?? $url);

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

    private function request(string $method, string $url): Response
    {
        return $this->http
            ->withUserAgent(sprintf('%s/1.0 (+%s)', str_replace(' ', '', (string) $this->config->get('app.name')), (string) $this->config->get('app.url')))
            ->timeout(15)
            ->connectTimeout(5)
            ->withOptions(['allow_redirects' => ['max' => 5, 'track_redirects' => true]])
            ->{$method}($url);
    }
}
