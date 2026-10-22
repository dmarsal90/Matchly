<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\GetOnBoard;

use App\Modules\Providers\Domain\Contracts\JobProvider;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Http\ProviderHttpClient;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Public API (no key): remote search, 100 per page, with company, tags, seniority and modality expanded.
 */
final readonly class GetOnBoardProvider implements JobProvider
{
    public function __construct(private ProviderHttpClient $client, private Config $config) {}

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'getonboard',
            name: 'Get on Board',
            homepageUrl: 'https://www.getonbrd.com',
            remoteOnly: true,
        );
    }

    public function fetch(): iterable
    {
        $options = (array) $this->config->get('jobs.providers.getonboard.options');

        for ($page = 1; $page <= (int) ($options['max_pages'] ?? 5); $page++) {
            $response = Payload::of($this->client->getJson('getonboard', (string) $options['url'], [
                'query' => '*',
                'remote' => 'true',
                'per_page' => 100,
                'page' => $page,
                'expand' => '["company","tags","seniority","modality"]',
            ]));

            foreach ($response->array('data') as $job) {
                if (is_array($job) && isset($job['id'])) {
                    yield new RawJob('getonboard', (string) $job['id'], $job);
                }
            }

            if ($page >= (int) Payload::of($response->array('meta'))->float('total_pages')) {
                return;
            }
        }
    }
}
