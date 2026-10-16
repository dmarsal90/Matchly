<?php

declare(strict_types=1);

namespace App\Modules\Applications\Infrastructure\Web;

use App\Modules\Shared\Infrastructure\Http\PublicUrlGuard;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as Http;
use Throwable;

/**
 * Reads one public page of a company site. The URL comes from the user, so
 * every hop is SSRF-checked, the connection is pinned to the validated IP,
 * redirects are followed by hand and only small HTML or text answers are kept.
 */
final readonly class PublicPageFetcher
{
    private const int MAX_REDIRECTS = 3;

    private const int MAX_BYTES = 400_000;

    public function __construct(private Http $http, private Config $config, private PublicUrlGuard $guard) {}

    /** The agent name sites see (and robots.txt addresses): the product name from APP_NAME. */
    public function agent(): string
    {
        return str_replace(' ', '', (string) $this->config->get('app.name')).'Bot';
    }

    /**
     * @return array{url: string, body: string}|null null when the page is unreachable, unsafe or not text
     */
    public function get(string $url): ?array
    {
        $current = $url;

        try {
            for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
                $target = $this->guard->resolve($current);
                $ip = str_contains($target['ip'], ':') ? "[{$target['ip']}]" : $target['ip'];

                $response = $this->http
                    ->withUserAgent(sprintf('%s/1.0 (+%s)', $this->agent(), (string) $this->config->get('app.url')))
                    ->accept('text/html,text/plain;q=0.9')
                    ->timeout(6)
                    ->connectTimeout(4)
                    ->withOptions([
                        'allow_redirects' => false,
                        'curl' => [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$ip}"], CURLOPT_MAXFILESIZE => self::MAX_BYTES * 4],
                    ])
                    ->get($current);

                if ($response->redirect() && $response->header('Location') !== '') {
                    $current = (string) UriResolver::resolve(Utils::uriFor($current), Utils::uriFor($response->header('Location')));

                    continue;
                }

                $type = strtolower($response->header('Content-Type'));
                if (! $response->successful() || ($type !== '' && ! str_contains($type, 'text/'))) {
                    return null;
                }

                return ['url' => $current, 'body' => mb_strcut($response->body(), 0, self::MAX_BYTES)];
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
