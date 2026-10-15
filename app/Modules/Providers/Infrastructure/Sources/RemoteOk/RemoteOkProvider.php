<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\RemoteOk;

use App\Modules\Providers\Domain\Contracts\JobProvider;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Http\ProviderHttpClient;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Terms: followable link back to the Remote OK listing and mention Remote OK
 * as the source. The first array element is the legal notice, not a job.
 */
final readonly class RemoteOkProvider implements JobProvider
{
    public function __construct(private ProviderHttpClient $client, private Config $config) {}

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'remoteok',
            name: 'Remote OK',
            homepageUrl: 'https://remoteok.com',
            termsUrl: 'https://remoteok.com/api',
            remoteOnly: true,
        );
    }

    public function fetch(): iterable
    {
        $response = $this->client->getJson('remoteok', (string) $this->config->get('jobs.providers.remoteok.options.url'));

        foreach ($response as $job) {
            if (is_array($job) && isset($job['id']) && ! isset($job['legal'])) {
                yield new RawJob('remoteok', (string) $job['id'], $job);
            }
        }
    }
}
