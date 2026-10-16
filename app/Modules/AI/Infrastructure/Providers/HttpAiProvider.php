<?php

declare(strict_types=1);

namespace App\Modules\AI\Infrastructure\Providers;

use App\Modules\AI\Domain\Contracts\AiProvider;
use App\Modules\AI\Domain\Data\AiCompletion;
use App\Modules\AI\Domain\Enums\ModelTier;
use App\Modules\AI\Domain\Exceptions\AiProviderFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

/** Shared configuration and HTTP plumbing of the vendor adapters. */
abstract class HttpAiProvider implements AiProvider
{
    /**
     * @param  array{api_key?: string|null, base_url?: string|null, models: array<string, string>, fallback_models?: list<string>, user_keys?: bool, keyless?: bool, version?: string}  $config
     */
    public function __construct(
        protected readonly Http $http,
        protected readonly string $providerName,
        protected readonly array $config,
        protected readonly int $timeout,
    ) {}

    public function name(): string
    {
        return $this->providerName;
    }

    public function model(ModelTier $tier): string
    {
        return $this->config['models'][$tier->value] ?? '';
    }

    public function available(?string $userKey = null): bool
    {
        if (blank($this->config['base_url'] ?? null)) {
            return false;
        }

        return ($this->config['keyless'] ?? false)
            || filled($this->config['api_key'] ?? null)
            || ($this->acceptsUserKeys() && filled($userKey));
    }

    public function acceptsUserKeys(): bool
    {
        return (bool) ($this->config['user_keys'] ?? false);
    }

    /** The user's own key, else the next configured key (a comma-separated list is rotated). */
    protected function key(AiCompletion $completion): string
    {
        if ($completion->apiKey !== null) {
            return $completion->apiKey;
        }

        $keys = array_values(array_filter(array_map(trim(...), explode(',', (string) ($this->config['api_key'] ?? '')))));
        if (count($keys) < 2) {
            return $keys[0] ?? '';
        }

        $cacheKey = "ai:key-rotation:{$this->providerName}";
        Cache::add($cacheKey, 0, 86400);

        return $keys[((int) Cache::increment($cacheKey)) % count($keys)];
    }

    protected function url(string $path): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/').'/'.ltrim($path, '/');
    }

    /** System prompt plus the schema, for vendors without native structured output. */
    protected function systemWithSchema(AiCompletion $completion): string
    {
        return $completion->system."\n\nAnswer with a single JSON object, no prose, that validates against this JSON Schema:\n"
            .json_encode($completion->schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  callable(PendingRequest): PendingRequest  $authenticate
     * @return array<string, mixed>
     */
    protected function post(string $url, array $body, callable $authenticate): array
    {
        try {
            $response = $authenticate($this->http->timeout($this->timeout)->acceptJson()->asJson())->post($url, $body);
        } catch (ConnectionException $exception) {
            throw new AiProviderFailed("{$this->providerName} is unreachable.", retryable: true, previous: $exception);
        }

        return $this->decode($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        if (! $response->successful()) {
            throw AiProviderFailed::fromStatus($this->providerName, $response->status(), $response->body());
        }

        $json = $response->json();

        return is_array($json) ? $json : throw new AiProviderFailed("{$this->providerName} returned a non-JSON response.");
    }
}
