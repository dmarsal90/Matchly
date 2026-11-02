<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\UsaJobs;

use App\Modules\Providers\Domain\Contracts\JobProvider;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Http\ProviderHttpClient;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use Illuminate\Contracts\Config\Repository as Config;
use RuntimeException;

/**
 * US federal government postings flagged remote that any US citizen may apply to.
 * Free key; the request must carry the email used to get it as User-Agent.
 */
final readonly class UsaJobsProvider implements JobProvider
{
    public function __construct(private ProviderHttpClient $client, private Config $config) {}

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'usajobs',
            name: 'USAJOBS',
            homepageUrl: 'https://www.usajobs.gov',
            termsUrl: 'https://developer.usajobs.gov/guides/terms-of-service',
        );
    }

    public function fetch(): iterable
    {
        $options = (array) $this->config->get('jobs.providers.usajobs.options');

        if (empty($options['api_key']) || empty($options['email'])) {
            throw new RuntimeException('USAJOBS is enabled but USAJOBS_API_KEY / USAJOBS_EMAIL are not set.');
        }

        $headers = ['User-Agent' => (string) $options['email'], 'Authorization-Key' => (string) $options['api_key']];
        $perPage = (int) ($options['per_page'] ?? 100);

        for ($page = 1; $page <= (int) ($options['max_pages'] ?? 3); $page++) {
            $response = Payload::of($this->client->getJson('usajobs', (string) $options['url'], [
                'RemoteIndicator' => 'True',
                'WhoMayApply' => 'public',
                'DatePosted' => min(60, (int) $this->config->get('jobs.max_age_days')),
                'ResultsPerPage' => $perPage,
                'Page' => $page,
            ], $headers));

            $items = $response->array('SearchResult.SearchResultItems');
            foreach ($items as $item) {
                $descriptor = is_array($item) ? ($item['MatchedObjectDescriptor'] ?? null) : null;
                $id = is_array($item) ? ($item['MatchedObjectId'] ?? $descriptor['PositionID'] ?? null) : null;

                if (is_array($descriptor) && $id !== null) {
                    yield new RawJob('usajobs', (string) $id, $descriptor);
                }
            }

            if (count($items) < $perPage) {
                return;
            }
        }
    }
}
