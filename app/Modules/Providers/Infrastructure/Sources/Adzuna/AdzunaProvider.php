<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Adzuna;

use App\Modules\Providers\Domain\Contracts\JobProvider;
use App\Modules\Providers\Domain\Contracts\ReportsPartialFailures;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Http\ProviderHttpClient;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use Illuminate\Contracts\Config\Repository as Config;
use RuntimeException;
use Throwable;

/**
 * Keyword search ("remote") per country. Requires a free app id/key;
 * terms require a visible "Jobs by Adzuna" attribution.
 */
final class AdzunaProvider implements JobProvider, ReportsPartialFailures
{
    private const string URL = 'https://api.adzuna.com/v1/api/jobs/%s/search/%d';

    /** @var list<string> */
    private array $failures = [];

    public function __construct(
        private readonly ProviderHttpClient $client,
        private readonly Config $config,
    ) {}

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'adzuna',
            name: 'Adzuna',
            homepageUrl: 'https://www.adzuna.com',
            termsUrl: 'https://developer.adzuna.com/docs/terms_of_service',
        );
    }

    public function fetch(): iterable
    {
        $this->failures = [];
        $options = (array) $this->config->get('jobs.providers.adzuna.options');

        if (empty($options['app_id']) || empty($options['app_key'])) {
            throw new RuntimeException('Adzuna is enabled but ADZUNA_APP_ID / ADZUNA_APP_KEY are not set.');
        }

        /** @var list<string> $countries */
        $countries = (array) ($options['countries'] ?? []);

        foreach ($countries as $country) {
            try {
                for ($page = 1; $page <= (int) ($options['max_pages'] ?? 2); $page++) {
                    $response = Payload::of($this->client->getJson('adzuna', sprintf(self::URL, $country, $page), [
                        'app_id' => (string) $options['app_id'],
                        'app_key' => (string) $options['app_key'],
                        'what' => (string) ($options['what'] ?? 'remote'),
                        'results_per_page' => (int) ($options['per_page'] ?? 50),
                        'max_days_old' => (int) $this->config->get('jobs.max_age_days'),
                        'sort_by' => 'date',
                        'content-type' => 'application/json',
                    ]));

                    $results = $response->array('results');
                    foreach ($results as $job) {
                        if (is_array($job) && isset($job['id'])) {
                            yield new RawJob('adzuna', "{$country}:{$job['id']}", [...$job, '_country' => $country]);
                        }
                    }

                    if (count($results) < (int) ($options['per_page'] ?? 50)) {
                        break;
                    }
                }
            } catch (Throwable $exception) {
                $this->failures[] = "{$country}: {$exception->getMessage()}";
            }
        }
    }

    public function failures(): array
    {
        return $this->failures;
    }
}
