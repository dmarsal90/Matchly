<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Services;

/**
 * Normalizes a URL so the same posting reached through different links
 * (tracking parameters, www, trailing slash, fragments) compares equal.
 */
final class CanonicalUrl
{
    private const array TRACKING_PARAMETERS = [
        'ref', 'referrer', 'source', 'src', 'gh_src', 'gh_jid', 'lever-source', 'lever-origin',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id',
        'fbclid', 'gclid', 'mc_cid', 'mc_eid', 'oga', 'trid', 'iis', 'iisn',
    ];

    public static function from(string $url): string
    {
        $parts = parse_url(trim($url));

        if ($parts === false || ! isset($parts['host'])) {
            return strtolower(trim($url));
        }

        $host = strtolower((string) preg_replace('/^www\./i', '', $parts['host']));
        $path = rtrim($parts['path'] ?? '', '/');

        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $query = array_filter(
            $query,
            fn (int|string $key): bool => ! in_array(strtolower((string) $key), self::TRACKING_PARAMETERS, true)
                && ! str_starts_with(strtolower((string) $key), 'utm_'),
            ARRAY_FILTER_USE_KEY,
        );
        ksort($query);

        $canonical = 'https://'.$host.$path;

        return $query === [] ? $canonical : $canonical.'?'.http_build_query($query);
    }
}
