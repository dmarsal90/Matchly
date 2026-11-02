<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\TheMuse;

use App\Modules\Providers\Domain\Contracts\JobProvider;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Http\ProviderHttpClient;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Only the "Flexible / Remote" location. Works without a key (500 requests/hour);
 * a free key raises the limit to 3,600.
 */
final readonly class TheMuseProvider implements JobProvider
{
    public function __construct(private ProviderHttpClient $client, private Config $config) {}

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'themuse',
            name: 'The Muse',
            homepageUrl: 'https://www.themuse.com',
            termsUrl: 'https://www.themuse.com/developers/api/v2',
        );
    }

    public function fetch(): iterable
    {
        $options = (array) $this->config->get('jobs.providers.themuse.options');

        for ($page = 1; $page <= (int) ($options['max_pages'] ?? 5); $page++) {
            $response = Payload::of($this->client->getJson('themuse', (string) $options['url'], array_filter([
                'page' => $page,
                'location' => 'Flexible / Remote',
                'api_key' => $options['api_key'] ?? null,
            ])));

            foreach ($response->array('results') as $job) {
                if (is_array($job) && isset($job['id'])) {
                    yield new RawJob('themuse', (string) $job['id'], $job);
                }
            }

            if ($page >= (int) ($response->string('page_count') ?? 0)) {
                return;
            }
        }
    }
}
