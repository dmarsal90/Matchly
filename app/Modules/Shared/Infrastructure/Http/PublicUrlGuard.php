<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Http;

use App\Modules\Shared\Domain\Exceptions\UnsafeUrl;
use Closure;

/**
 * SSRF guard for URLs that come from third parties (job feeds, user input).
 * Only http(s) URLs whose host resolves exclusively to public addresses pass;
 * callers pin the connection to the returned IP so DNS cannot change between
 * the check and the request (rebinding).
 */
final class PublicUrlGuard
{
    private const array BLOCKED_HOST_SUFFIXES = ['localhost', '.local', '.internal', '.localdomain', '.home.arpa', '.lan'];

    /** @var Closure(string): list<string> */
    private Closure $resolver;

    /**
     * @param  (Closure(string): list<string>)|null  $resolver  host => IP addresses
     */
    public function __construct(?Closure $resolver = null)
    {
        $this->resolver = $resolver ?? self::systemResolver(...);
    }

    /**
     * Cheap check without DNS, used at ingestion time: scheme, credentials,
     * obviously internal host names and private IP literals.
     */
    public function looksPublic(string $url): bool
    {
        try {
            $this->parse($url);

            return true;
        } catch (UnsafeUrl) {
            return false;
        }
    }

    /**
     * Full check with DNS resolution.
     *
     * @return array{host: string, port: int, ip: string}
     *
     * @throws UnsafeUrl
     */
    public function resolve(string $url): array
    {
        [$host, $port] = $this->parse($url);

        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : ($this->resolver)($host);

        if ($ips === []) {
            throw UnsafeUrl::because($url, 'host does not resolve');
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                throw UnsafeUrl::because($url, "host resolves to non-public address {$ip}");
            }
        }

        return ['host' => $host, 'port' => $port, 'ip' => $ips[0]];
    }

    public static function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            // Ranges PHP's flags miss: CGNAT, IPv4-mapped IPv6, unique-local and site-local IPv6.
            && preg_match('/^(100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.|::ffff:|f[cd][0-9a-f]{2}:|fec0:)/i', $ip) !== 1;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function parse(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw UnsafeUrl::because($url, 'not an absolute URL');
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw UnsafeUrl::because($url, "scheme {$scheme} is not allowed");
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw UnsafeUrl::because($url, 'credentials in URL');
        }

        $host = strtolower(trim($parts['host'], '[]'));
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        if (! in_array($port, [80, 443, 8080, 8443], true)) {
            throw UnsafeUrl::because($url, "port {$port} is not allowed");
        }

        foreach (self::BLOCKED_HOST_SUFFIXES as $suffix) {
            if ($host === ltrim($suffix, '.') || str_ends_with($host, $suffix)) {
                throw UnsafeUrl::because($url, 'internal host name');
            }
        }

        // Single-label names ("redis", "postgres") are Docker/LAN services, never public sites.
        if (! str_contains($host, '.') && filter_var($host, FILTER_VALIDATE_IP) === false) {
            throw UnsafeUrl::because($url, 'single-label host name');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false && ! self::isPublicIp($host)) {
            throw UnsafeUrl::because($url, 'non-public IP literal');
        }

        return [$host, $port];
    }

    /**
     * @return list<string>
     */
    private static function systemResolver(string $host): array
    {
        $ips = gethostbynamel($host) ?: [];

        $aaaa = @dns_get_record($host, DNS_AAAA) ?: [];
        foreach ($aaaa as $record) {
            if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }
}
