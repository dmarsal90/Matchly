<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\WorkingNomads;

use App\Modules\Providers\Domain\Contracts\JobProvider;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Http\ProviderHttpClient;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Public feed of the latest listings; the `url` is a Working Nomads redirect to the employer.
 */
final readonly class WorkingNomadsProvider implements JobProvider
{
    public function __construct(private ProviderHttpClient $client, private Config $config) {}

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'workingnomads',
            name: 'Working Nomads',
            homepageUrl: 'https://www.workingnomads.com',
            remoteOnly: true,
        );
    }

    public function fetch(): iterable
    {
        $response = $this->client->getJson('workingnomads', (string) $this->config->get('jobs.providers.workingnomads.options.url'));

        foreach ($response as $job) {
            if (is_array($job) && isset($job['url'])) {
                yield new RawJob('workingnomads', (string) $job['url'], $job);
            }
        }
    }
}
