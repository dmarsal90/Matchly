<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\RemoteLanders;

use App\Modules\Providers\Domain\Contracts\JobProvider;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Http\ProviderHttpClient;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Paginated JSON, newest first. The list carries no description; the listing page does.
 */
final readonly class RemoteLandersProvider implements JobProvider
{
    public function __construct(private ProviderHttpClient $client, private Config $config) {}

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'remotelanders',
            name: 'Remote Landers',
            homepageUrl: 'https://remotelanders.com',
            remoteOnly: true,
        );
    }

    public function fetch(): iterable
    {
        $options = (array) $this->config->get('jobs.providers.remotelanders.options');

        for ($page = 1; $page <= (int) ($options['max_pages'] ?? 5); $page++) {
            $response = Payload::of($this->client->getJson('remotelanders', (string) $options['url'], ['page' => $page, 'limit' => 100]));
            $jobs = $response->array('jobs');

            foreach ($jobs as $job) {
                if (is_array($job) && isset($job['slug'])) {
                    yield new RawJob('remotelanders', (string) $job['slug'], $job);
                }
            }

            if (count($jobs) < 100) {
                return;
            }
        }
    }
}
