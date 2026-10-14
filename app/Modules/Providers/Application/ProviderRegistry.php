<?php

declare(strict_types=1);

namespace App\Modules\Providers\Application;

use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Contracts\JobProvider;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves the provider/normalizer pair of each source from config/jobs.php.
 */
final readonly class ProviderRegistry
{
    public function __construct(private Container $container, private Config $config) {}

    /**
     * @return list<string> keys of every configured source
     */
    public function keys(): array
    {
        return array_keys((array) $this->config->get('jobs.providers', []));
    }

    /**
     * @return list<string> keys of sources switched on in config
     */
    public function enabled(): array
    {
        return array_values(array_filter($this->keys(), fn (string $key): bool => (bool) $this->setting($key, 'enabled')));
    }

    public function has(string $key): bool
    {
        return in_array($key, $this->keys(), true);
    }

    public function provider(string $key): JobProvider
    {
        $provider = $this->container->make((string) $this->setting($key, 'provider'));

        if (! $provider instanceof JobProvider) {
            throw new InvalidArgumentException("Source [{$key}] provider must implement ".JobProvider::class);
        }

        return $provider;
    }

    public function normalizer(string $key): JobNormalizer
    {
        $normalizer = $this->container->make((string) $this->setting($key, 'normalizer'));

        if (! $normalizer instanceof JobNormalizer) {
            throw new InvalidArgumentException("Source [{$key}] normalizer must implement ".JobNormalizer::class);
        }

        return $normalizer;
    }

    public function intervalMinutes(string $key): int
    {
        return max(1, (int) $this->setting($key, 'interval'));
    }

    private function setting(string $key, string $name): mixed
    {
        if (! $this->has($key)) {
            throw new InvalidArgumentException("Unknown job source [{$key}].");
        }

        return $this->config->get("jobs.providers.{$key}.{$name}");
    }
}
