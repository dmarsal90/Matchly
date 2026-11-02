<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Reed;

use App\Modules\Providers\Domain\Contracts\JobProvider;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Http\ProviderHttpClient;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use Illuminate\Contracts\Config\Repository as Config;
use RuntimeException;

/**
 * UK keyword search ("remote"). Free key; the API returns at most 100 results per request.
 */
final readonly class ReedProvider implements JobProvider
{
    public function __construct(private ProviderHttpClient $client, private Config $config) {}

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'reed',
            name: 'Reed',
            homepageUrl: 'https://www.reed.co.uk',
            termsUrl: 'https://www.reed.co.uk/developers/jobseeker',
        );
    }

    public function fetch(): iterable
    {
        $options = (array) $this->config->get('jobs.providers.reed.options');

        if (empty($options['api_key'])) {
            throw new RuntimeException('Reed is enabled but REED_API_KEY is not set.');
        }

        // The key is the basic-auth username; the password stays empty.
        $headers = ['Authorization' => 'Basic '.base64_encode($options['api_key'].':')];
        $limit = (int) ($options['per_page'] ?? 100);

        for ($page = 0; $page < (int) ($options['max_pages'] ?? 3); $page++) {
            $response = Payload::of($this->client->getJson('reed', (string) $options['url'], [
                'keywords' => (string) ($options['keywords'] ?? 'remote'),
                'resultsToTake' => $limit,
                'resultsToSkip' => $page * $limit,
            ], $headers));

            $jobs = $response->array('results');
            foreach ($jobs as $job) {
                if (is_array($job) && isset($job['jobId'])) {
                    yield new RawJob('reed', (string) $job['jobId'], $job);
                }
            }

            if (count($jobs) < $limit) {
                return;
            }
        }
    }
}
