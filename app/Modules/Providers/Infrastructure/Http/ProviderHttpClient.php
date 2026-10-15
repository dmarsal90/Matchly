<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Http;

use App\Modules\Providers\Domain\Exceptions\ProviderRequestFailed;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use JsonException;
use Throwable;

/**
 * The only way sources talk to the outside world: per-provider throttling,
 * retries with exponential back-off on 429/5xx/network errors, short-lived
 * response caching (so a retried job does not hammer the API) and ETag
 * revalidation where the server supports it.
 */
final class ProviderHttpClient
{
    private const int ETAG_TTL_SECONDS = 86400;

    public function __construct(
        private readonly Http $http,
        private readonly Cache $cache,
        private readonly RateLimiter $limiter,
        private readonly Config $config,
    ) {}

    /**
     * @param  array<string, scalar|null>  $query
     * @return array<mixed>
     */
    public function getJson(string $provider, string $url, array $query = []): array
    {
        $body = $this->get($provider, $url, $query, 'application/json');
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw ProviderRequestFailed::for($provider, $url, null, new JsonException('Response is not a JSON document'));
        }

        return $decoded;
    }

    /**
     * @param  array<string, scalar|null>  $query
     */
    public function getText(string $provider, string $url, array $query = []): string
    {
        return $this->get($provider, $url, $query, 'application/rss+xml, application/xml;q=0.9, */*;q=0.8');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<mixed>
     */
    public function postJson(string $provider, string $url, array $payload): array
    {
        $cacheKey = $this->cacheKey('POST', $url, $payload);

        $body = $this->cache->get($cacheKey);
        if (! is_string($body)) {
            $response = $this->send($provider, $url, fn (PendingRequest $request): Response => $request->post($url, $payload));
            $body = $response->body();
            $this->cache->put($cacheKey, $body, $this->cacheTtl($provider));
        }

        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw ProviderRequestFailed::for($provider, $url, null, new JsonException('Response is not a JSON document'));
        }

        return $decoded;
    }

    /**
     * @param  array<string, scalar|null>  $query
     */
    private function get(string $provider, string $url, array $query, string $accept): string
    {
        $cacheKey = $this->cacheKey('GET', $url, $query);

        $cached = $this->cache->get($cacheKey);
        if (is_string($cached)) {
            return $cached;
        }

        $etagKey = $cacheKey.':etag';
        /** @var array{etag: string, body: string}|null $validator */
        $validator = $this->cache->get($etagKey);

        $response = $this->send($provider, $url, function (PendingRequest $request) use ($url, $query, $accept, $validator): Response {
            $request->accept($accept);

            if (is_array($validator)) {
                $request->withHeaders(['If-None-Match' => $validator['etag']]);
            }

            return $request->get($url, $query);
        });

        $body = $response->status() === 304 && is_array($validator) ? $validator['body'] : $response->body();

        $this->cache->put($cacheKey, $body, $this->cacheTtl($provider));

        $etag = $response->header('ETag');
        if ($etag !== '') {
            $this->cache->put($etagKey, ['etag' => $etag, 'body' => $body], self::ETAG_TTL_SECONDS);
        }

        return $body;
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(string $provider, string $url, callable $call): Response
    {
        $this->throttle($provider);

        $request = $this->http
            ->withUserAgent($this->userAgent())
            ->timeout((int) $this->config->get('jobs.http.timeout', 20))
            ->connectTimeout((int) $this->config->get('jobs.http.connect_timeout', 10))
            ->retry(
                (int) $this->config->get('jobs.http.retries', 3),
                fn (int $attempt): int => (int) $this->config->get('jobs.http.retry_base_ms', 500) * (2 ** ($attempt - 1)),
                // Called with null for non-2xx responses that are not errors (304 Not Modified).
                fn (?Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && in_array($exception->response->status(), [408, 425, 429, 500, 502, 503, 504], true)),
            );

        try {
            $response = $call($request);
        } catch (RequestException $exception) {
            throw ProviderRequestFailed::for($provider, $url, $exception->response->status(), $exception);
        } catch (ConnectionException $exception) {
            throw ProviderRequestFailed::for($provider, $url, null, $exception);
        }

        if ($response->failed() && $response->status() !== 304) {
            throw ProviderRequestFailed::for($provider, $url, $response->status());
        }

        return $response;
    }

    /**
     * Waits for a free slot in the provider's per-minute budget, so a long
     * paginated fetch slows down instead of getting banned.
     */
    private function throttle(string $provider): void
    {
        $perMinute = max(1, (int) $this->config->get("jobs.providers.{$provider}.rate_limit", 30));
        $maxWait = (int) $this->config->get('jobs.http.max_throttle_wait_seconds', 60);
        $key = "provider-http:{$provider}";

        $waited = 0;
        while ($this->limiter->tooManyAttempts($key, $perMinute)) {
            $seconds = max(1, $this->limiter->availableIn($key));
            if ($waited + $seconds > $maxWait) {
                throw ProviderRequestFailed::for($provider, $key, 429);
            }
            sleep($seconds);
            $waited += $seconds;
        }

        $this->limiter->hit($key, 60);
    }

    private function cacheTtl(string $provider): int
    {
        return (int) $this->config->get("jobs.providers.{$provider}.cache_ttl", 600);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function cacheKey(string $method, string $url, array $parameters): string
    {
        return 'provider-http:'.hash('xxh128', $method.' '.$url.' '.json_encode($parameters));
    }

    private function userAgent(): string
    {
        return sprintf('%s/1.0 (+%s)', str_replace(' ', '', (string) $this->config->get('app.name')), (string) $this->config->get('app.url'));
    }
}
