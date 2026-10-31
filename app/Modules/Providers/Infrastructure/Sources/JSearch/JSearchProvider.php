<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\JSearch;

use App\Modules\Providers\Domain\Contracts\JobProvider;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Http\ProviderHttpClient;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use Illuminate\Contracts\Config\Repository as Config;
use RuntimeException;

/**
 * One global keyword search per run: the free plan is only 200 requests a month.
 */
class JSearchProvider implements JobProvider
{
    public function __construct(
        protected readonly ProviderHttpClient $client,
        protected readonly Config $config,
    ) {}

    protected function key(): string
    {
        return 'jsearch';
    }

    protected function url(): string
    {
        return 'https://api.openwebninja.com/jsearch/search-v2';
    }

    /** Where the job list sits in the response. */
    protected function jobsPath(): string
    {
        return 'data.jobs';
    }

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: $this->key(),
            name: 'JSearch',
            homepageUrl: 'https://www.openwebninja.com/api/jsearch',
            termsUrl: 'https://www.openwebninja.com/terms',
        );
    }

    public function fetch(): iterable
    {
        $options = (array) $this->config->get("jobs.providers.{$this->key()}.options");

        if (empty($options['api_key'])) {
            throw new RuntimeException("{$this->key()} is enabled but its API key is not set.");
        }

        $response = Payload::of($this->client->getJson($this->key(), $this->url(), [
            'query' => (string) ($options['query'] ?? 'remote developer'),
            'num_pages' => (int) ($options['num_pages'] ?? 1),
            'work_from_home' => 'true',
        ], ['X-API-Key' => (string) $options['api_key']]));

        foreach ($response->array($this->jobsPath()) as $job) {
            if (is_array($job) && isset($job['job_id'])) {
                yield new RawJob($this->key(), (string) $job['job_id'], $job);
            }
        }
    }
}
