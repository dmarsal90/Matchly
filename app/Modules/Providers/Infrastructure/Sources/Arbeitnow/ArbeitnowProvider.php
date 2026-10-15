<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Arbeitnow;

use App\Modules\Providers\Domain\Contracts\JobProvider;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Http\ProviderHttpClient;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Mostly on-site jobs in Germany; only postings flagged `remote` are kept.
 */
final readonly class ArbeitnowProvider implements JobProvider
{
    public function __construct(private ProviderHttpClient $client, private Config $config) {}

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'arbeitnow',
            name: 'Arbeitnow',
            homepageUrl: 'https://www.arbeitnow.com',
            termsUrl: 'https://www.arbeitnow.com/blog/job-board-api',
        );
    }

    public function fetch(): iterable
    {
        $options = (array) $this->config->get('jobs.providers.arbeitnow.options');

        for ($page = 1; $page <= (int) ($options['max_pages'] ?? 5); $page++) {
            $response = Payload::of($this->client->getJson('arbeitnow', (string) $options['url'], ['page' => $page]));

            foreach ($response->array('data') as $job) {
                if (is_array($job) && isset($job['slug']) && ($job['remote'] ?? false) === true) {
                    yield new RawJob('arbeitnow', (string) $job['slug'], $job);
                }
            }

            if ($response->string('links.next') === null) {
                return;
            }
        }
    }
}
