<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\PuenteTalent;

use App\Modules\Providers\Domain\Contracts\JobProvider;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Http\ProviderHttpClient;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Remote US roles open to Latin American candidates. The endpoint is the one the
 * Puente site itself uses (undocumented, allowed by its robots.txt); `page=N`
 * returns the first N*6 jobs, so a single large page fetches everything.
 */
final readonly class PuenteTalentProvider implements JobProvider
{
    public function __construct(private ProviderHttpClient $client, private Config $config) {}

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'puentetalent',
            name: 'Puente Talent',
            homepageUrl: 'https://puentetalent.com',
            remoteOnly: true,
        );
    }

    public function fetch(): iterable
    {
        $options = (array) $this->config->get('jobs.providers.puentetalent.options');

        $response = Payload::of($this->client->getJson('puentetalent', (string) $options['url'], ['page' => (int) ($options['pages'] ?? 20)]));

        foreach ($response->array('jobs') as $job) {
            if (is_array($job) && isset($job['displayId']) && ($job['isActive'] ?? true) === true) {
                yield new RawJob('puentetalent', (string) $job['displayId'], $job);
            }
        }
    }
}
