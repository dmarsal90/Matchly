<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Arbetsformedlingen;

use App\Modules\Providers\Domain\Contracts\JobProvider;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Http\ProviderHttpClient;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Swedish Public Employment Service (JobTech JobSearch), open data without a key.
 * Only postings the employer flagged as remote (`remote=true`).
 */
final readonly class ArbetsformedlingenProvider implements JobProvider
{
    public function __construct(private ProviderHttpClient $client, private Config $config) {}

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'arbetsformedlingen',
            name: 'Arbetsförmedlingen',
            homepageUrl: 'https://arbetsformedlingen.se',
            termsUrl: 'https://jobtechdev.se/en/components/jobsearch',
        );
    }

    public function fetch(): iterable
    {
        $options = (array) $this->config->get('jobs.providers.arbetsformedlingen.options');
        $limit = (int) ($options['per_page'] ?? 100);

        for ($page = 0; $page < (int) ($options['max_pages'] ?? 3); $page++) {
            $response = Payload::of($this->client->getJson('arbetsformedlingen', (string) $options['url'], [
                'remote' => 'true',
                'sort' => 'pubdate-desc',
                'limit' => $limit,
                'offset' => $page * $limit,
            ]));

            $hits = $response->array('hits');
            foreach ($hits as $job) {
                if (is_array($job) && isset($job['id'])) {
                    yield new RawJob('arbetsformedlingen', (string) $job['id'], $job);
                }
            }

            if (count($hits) < $limit) {
                return;
            }
        }
    }
}
