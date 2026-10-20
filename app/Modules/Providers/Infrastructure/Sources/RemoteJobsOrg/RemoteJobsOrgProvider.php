<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\RemoteJobsOrg;

use App\Modules\Providers\Domain\Contracts\JobProvider;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Http\ProviderHttpClient;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Free read-only catalog API, no key. Terms: credit RemoteJobs.org and link to its listing.
 */
final readonly class RemoteJobsOrgProvider implements JobProvider
{
    public function __construct(private ProviderHttpClient $client, private Config $config) {}

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'remotejobsorg',
            name: 'RemoteJobs.org',
            homepageUrl: 'https://remotejobs.org',
            termsUrl: 'https://remotejobs.org/developers/api',
            remoteOnly: true,
        );
    }

    public function fetch(): iterable
    {
        $options = (array) $this->config->get('jobs.providers.remotejobsorg.options');

        for ($page = 0; $page < (int) ($options['max_pages'] ?? 6); $page++) {
            $response = Payload::of($this->client->getJson('remotejobsorg', (string) $options['url'], ['limit' => 50, 'offset' => $page * 50]));

            foreach ($response->array('data') as $job) {
                if (is_array($job) && isset($job['id'])) {
                    yield new RawJob('remotejobsorg', (string) $job['id'], $job);
                }
            }

            if ($response->bool('pagination.has_more') !== true) {
                return;
            }
        }
    }
}
