<?php

declare(strict_types=1);

namespace App\Modules\Providers\Application;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Counts requests per quota window for sources whose plan caps them
 * (config jobs.providers.<key>.monthly_limit). No limit configured = unlimited.
 * The window is the calendar month, or quota_days-long periods counted from
 * quota_resets_on (the reset date shown in the provider's dashboard).
 */
final readonly class MonthlyQuota
{
    public function __construct(private Cache $cache, private Config $config) {}

    public function exhausted(string $source): bool
    {
        $limit = (int) $this->config->get("jobs.providers.{$source}.monthly_limit", 0);

        return $limit > 0 && $this->used($source) >= $limit;
    }

    public function used(string $source): int
    {
        return (int) $this->cache->get($this->key($source), 0);
    }

    public function hit(string $source): void
    {
        $key = $this->key($source);
        $this->cache->add($key, 0, now()->addDays(40));
        $this->cache->increment($key);
    }

    private function key(string $source): string
    {
        $anchor = $this->config->get("jobs.providers.{$source}.quota_resets_on");
        $days = max(1, (int) $this->config->get("jobs.providers.{$source}.quota_days", 30));

        $window = is_string($anchor) && $anchor !== ''
            ? (string) floor(CarbonImmutable::parse($anchor)->startOfDay()->diffInDays(now()->startOfDay(), false) / $days)
            : now()->format('Y-m');

        return "provider-quota:{$source}:{$window}";
    }
}
