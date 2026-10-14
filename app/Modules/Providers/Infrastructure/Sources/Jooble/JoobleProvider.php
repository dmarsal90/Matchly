<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Jooble;

use App\Modules\Providers\Domain\Contracts\JobProvider;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Http\ProviderHttpClient;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use Illuminate\Contracts\Config\Repository as Config;
use RuntimeException;

/** Keyword search through Jooble's free REST API (key required). */
final readonly class JoobleProvider implements JobProvider
{
    public function __construct(private ProviderHttpClient $client, private Config $config) {}

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'jooble',
            name: 'Jooble',
            homepageUrl: 'https://jooble.org',
            termsUrl: 'https://jooble.org/api/about',
        );
    }

    public function fetch(): iterable
    {
        $options = (array) $this->config->get('jobs.providers.jooble.options');

        if (empty($options['api_key'])) {
            throw new RuntimeException('Jooble is enabled but JOOBLE_API_KEY is not set.');
        }

        $url = 'https://jooble.org/api/'.rawurlencode((string) $options['api_key']);

        for ($page = 1; $page <= (int) ($options['max_pages'] ?? 3); $page++) {
            $response = Payload::of($this->client->postJson('jooble', $url, [
                'keywords' => (string) ($options['keywords'] ?? 'remote'),
                'page' => (string) $page,
            ]));

            $jobs = $response->array('jobs');
            foreach ($jobs as $job) {
                if (is_array($job) && isset($job['id'])) {
                    yield new RawJob('jooble', (string) $job['id'], $job);
                }
            }

            if ($jobs === []) {
                return;
            }
        }
    }
}
