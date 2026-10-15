<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Infrastructure\GitHub;

use App\Modules\Profiles\Domain\Exceptions\ImportFailed;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Read-only client for GitHub's official REST API, public endpoints only.
 *
 * Every response is cached with its ETag and revalidated with
 * If-None-Match: unchanged data comes back as a 304, which GitHub does not
 * count against an authorised rate limit. Rate-limit exhaustion is reported
 * as such instead of being retried.
 */
final readonly class GitHubClient
{
    private const string JSON = 'application/vnd.github+json';

    private const string RAW = 'application/vnd.github.raw+json';

    public function __construct(
        private Http $http,
        private Cache $cache,
        private string $baseUrl,
        private ?string $token,
        private int $timeout,
        private int $cacheTtl,
        private string $userAgent,
    ) {}

    public function withToken(?string $token): self
    {
        return new self($this->http, $this->cache, $this->baseUrl, $token ?? $this->token, $this->timeout, $this->cacheTtl, $this->userAgent);
    }

    public function authenticated(): bool
    {
        return $this->token !== null && $this->token !== '';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(string $username): ?array
    {
        return $this->json('/users/'.rawurlencode($username));
    }

    /**
     * Public repositories the user owns, most recently pushed first.
     *
     * @return list<array<string, mixed>>
     */
    public function repositories(string $username): array
    {
        $repositories = $this->json('/users/'.rawurlencode($username).'/repos', [
            'type' => 'owner',
            'sort' => 'pushed',
            'direction' => 'desc',
            'per_page' => 100,
        ]);

        return array_values(array_filter($repositories ?? [], 'is_array'));
    }

    /**
     * @return array<string, int> language => bytes
     */
    public function languages(string $owner, string $repository): array
    {
        $languages = $this->json($this->repoPath($owner, $repository).'/languages') ?? [];

        return array_filter(array_map(fn (mixed $bytes): int => is_numeric($bytes) ? (int) $bytes : 0, $languages));
    }

    /**
     * Files and folders at the repository root.
     *
     * @return list<array{name: string, type: string, size: int}>
     */
    public function rootContents(string $owner, string $repository): array
    {
        $entries = $this->json($this->repoPath($owner, $repository).'/contents/') ?? [];

        $files = [];
        foreach ($entries as $entry) {
            if (is_array($entry) && is_string($entry['name'] ?? null)) {
                $files[] = ['name' => $entry['name'], 'type' => (string) ($entry['type'] ?? 'file'), 'size' => (int) ($entry['size'] ?? 0)];
            }
        }

        return $files;
    }

    public function file(string $owner, string $repository, string $path): ?string
    {
        return $this->get($this->repoPath($owner, $repository).'/contents/'.implode('/', array_map(rawurlencode(...), explode('/', $path))), [], self::RAW);
    }

    public function readme(string $owner, string $repository): ?string
    {
        return $this->get($this->repoPath($owner, $repository).'/readme', [], self::RAW);
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<mixed>|null
     */
    private function json(string $path, array $query = []): ?array
    {
        $body = $this->get($path, $query, self::JSON);
        if ($body === null) {
            return null;
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : throw ImportFailed::because(ImportFailed::UNAVAILABLE, "GitHub returned invalid JSON for {$path}.");
    }

    /**
     * @param  array<string, scalar>  $query
     * @return string|null null when the resource does not exist
     */
    private function get(string $path, array $query, string $accept): ?string
    {
        $key = 'github:'.hash('xxh128', $path.'?'.http_build_query($query).'#'.$accept);

        /** @var array{etag: string, body: string|null}|null $cached */
        $cached = $this->cache->get($key);

        $request = $this->http
            ->baseUrl($this->baseUrl)
            ->withUserAgent($this->userAgent)
            ->withHeaders(array_filter([
                'Accept' => $accept,
                'X-GitHub-Api-Version' => '2022-11-28',
                'If-None-Match' => is_array($cached) ? $cached['etag'] : null,
            ]))
            ->timeout($this->timeout)
            ->retry(2, 500, fn (Throwable $exception): bool => $exception instanceof ConnectionException, throw: false);

        if ($this->authenticated()) {
            $request = $request->withToken((string) $this->token);
        }

        try {
            $response = $request->get($path, $query);
        } catch (ConnectionException $exception) {
            throw ImportFailed::because(ImportFailed::UNAVAILABLE, 'GitHub is unreachable.', $exception);
        }

        if ($response->status() === 304 && is_array($cached)) {
            return $cached['body'];
        }

        if ($this->rateLimited($response)) {
            throw ImportFailed::because(ImportFailed::RATE_LIMITED, 'GitHub API rate limit exhausted until '.$response->header('X-RateLimit-Reset').'.');
        }

        // Missing users/files, and empty repositories (409 on contents).
        if (in_array($response->status(), [404, 409, 451], true)) {
            $this->remember($key, $response, null);

            return null;
        }

        if (! $response->successful()) {
            throw ImportFailed::because(ImportFailed::UNAVAILABLE, "GitHub answered {$response->status()} for {$path}.");
        }

        $this->remember($key, $response, $response->body());

        return $response->body();
    }

    private function remember(string $key, Response $response, ?string $body): void
    {
        $etag = $response->header('ETag');
        if ($etag !== '') {
            $this->cache->put($key, ['etag' => $etag, 'body' => $body], $this->cacheTtl);
        }
    }

    private function rateLimited(Response $response): bool
    {
        return $response->status() === 429
            || ($response->status() === 403 && ($response->header('X-RateLimit-Remaining') === '0' || $response->header('Retry-After') !== ''));
    }

    private function repoPath(string $owner, string $repository): string
    {
        return '/repos/'.rawurlencode($owner).'/'.rawurlencode($repository);
    }
}
