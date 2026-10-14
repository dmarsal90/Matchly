<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Himalayas;

use App\Modules\Providers\Domain\Contracts\JobProvider;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Http\ProviderHttpClient;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Cursor pagination (offset is deprecated), at most 20 jobs per page,
 * newest first: stops at the freshness cutoff.
 */
final readonly class HimalayasProvider implements JobProvider
{
    public function __construct(private ProviderHttpClient $client, private Config $config) {}

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'himalayas',
            name: 'Himalayas',
            homepageUrl: 'https://himalayas.app',
            termsUrl: 'https://himalayas.app/api',
            remoteOnly: true,
        );
    }

    public function fetch(): iterable
    {
        $options = (array) $this->config->get('jobs.providers.himalayas.options');
        $cutoff = CarbonImmutable::now()->subDays((int) $this->config->get('jobs.max_age_days'));
        $cursor = null;

        for ($page = 1; $page <= (int) ($options['max_pages'] ?? 50); $page++) {
            $response = Payload::of($this->client->getJson('himalayas', (string) $options['url'], array_filter([
                'limit' => (int) ($options['per_page'] ?? 20),
                'cursor' => $cursor,
            ])));

            $jobs = $response->array('jobs');
            $reachedCutoff = false;

            foreach ($jobs as $job) {
                if (! is_array($job)) {
                    continue;
                }

                $payload = Payload::of($job);
                $id = $payload->string('guid') ?? $payload->string('applicationLink');
                $postedAt = $payload->date('pubDate');

                if ($postedAt !== null && $postedAt < $cutoff) {
                    $reachedCutoff = true;

                    continue;
                }

                if ($id !== null) {
                    yield new RawJob('himalayas', $id, $job);
                }
            }

            $cursor = $response->string('nextCursor');
            if ($reachedCutoff || $jobs === [] || $cursor === null) {
                return;
            }
        }
    }
}
