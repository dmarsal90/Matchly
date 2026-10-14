<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Jobicy;

use App\Modules\Providers\Domain\Contracts\JobProvider;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Http\ProviderHttpClient;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Terms: credit Jobicy with a direct link and send applications to the
 * original job URL. Cursor-paginated, newest first.
 */
final readonly class JobicyProvider implements JobProvider
{
    public function __construct(private ProviderHttpClient $client, private Config $config) {}

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'jobicy',
            name: 'Jobicy',
            homepageUrl: 'https://jobicy.com',
            termsUrl: 'https://jobicy.com/jobs-rss-feed',
            remoteOnly: true,
        );
    }

    public function fetch(): iterable
    {
        $options = (array) $this->config->get('jobs.providers.jobicy.options');
        $cutoff = CarbonImmutable::now()->subDays((int) $this->config->get('jobs.max_age_days'));
        $cursor = null;

        for ($page = 1; $page <= (int) ($options['max_pages'] ?? 5); $page++) {
            $response = Payload::of($this->client->getJson('jobicy', (string) $options['url'], array_filter([
                'count' => (int) ($options['per_page'] ?? 100),
                'cursor' => $cursor,
            ])));

            $reachedCutoff = false;
            foreach ($response->array('jobs') as $job) {
                if (! is_array($job) || ! isset($job['id'])) {
                    continue;
                }

                $postedAt = Payload::of($job)->date('pubDate');
                if ($postedAt !== null && $postedAt < $cutoff) {
                    $reachedCutoff = true;

                    continue;
                }

                yield new RawJob('jobicy', (string) $job['id'], $job);
            }

            $cursor = $response->string('nextCursor');
            if ($reachedCutoff || $cursor === null || $response->bool('hasMore') === false) {
                return;
            }
        }
    }
}
