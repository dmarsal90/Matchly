<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Remotive;

use App\Modules\Providers\Domain\Contracts\JobProvider;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Http\ProviderHttpClient;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Terms: link back to the Remotive listing and credit Remotive; at most four
 * requests a day; listings must not be used to collect sign-ups.
 */
final readonly class RemotiveProvider implements JobProvider
{
    public function __construct(private ProviderHttpClient $client, private Config $config) {}

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'remotive',
            name: 'Remotive',
            homepageUrl: 'https://remotive.com',
            termsUrl: 'https://remotive.com/api-documentation',
            remoteOnly: true,
        );
    }

    public function fetch(): iterable
    {
        $response = $this->client->getJson('remotive', (string) $this->config->get('jobs.providers.remotive.options.url'));

        foreach (Payload::of($response)->array('jobs') as $job) {
            if (is_array($job) && isset($job['id'])) {
                yield new RawJob('remotive', (string) $job['id'], $job);
            }
        }
    }
}
