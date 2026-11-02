<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\FranceTravail;

use App\Modules\Providers\Domain\Contracts\JobProvider;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Http\ProviderHttpClient;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as Http;
use RuntimeException;

/**
 * French public employment service. OAuth2 client credentials (free registration at
 * francetravail.io); the source must be cited. The API has no remote filter, so the
 * query is the keyword "télétravail". One query returns at most 1,150 offers.
 */
final readonly class FranceTravailProvider implements JobProvider
{
    private const int PAGE = 150;

    public function __construct(private ProviderHttpClient $client, private Config $config, private Http $http) {}

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'francetravail',
            name: 'France Travail',
            homepageUrl: 'https://www.francetravail.fr',
            termsUrl: 'https://francetravail.io/data/api/offres-emploi',
        );
    }

    public function fetch(): iterable
    {
        $options = (array) $this->config->get('jobs.providers.francetravail.options');

        if (empty($options['client_id']) || empty($options['client_secret'])) {
            throw new RuntimeException('France Travail is enabled but FRANCETRAVAIL_CLIENT_ID / FRANCETRAVAIL_CLIENT_SECRET are not set.');
        }

        // shortcut: token fetched once per run outside ProviderHttpClient (no form POST there); revisit if more OAuth sources appear.
        $token = (string) $this->http->asForm()->timeout(20)->post((string) $options['token_url'], [
            'grant_type' => 'client_credentials',
            'client_id' => $options['client_id'],
            'client_secret' => $options['client_secret'],
            'scope' => 'api_offresdemploiv2 o2dsoffre',
        ])->throw()->json('access_token');

        if ($token === '') {
            throw new RuntimeException('France Travail did not return an access token.');
        }

        for ($page = 0; $page < (int) ($options['max_pages'] ?? 3); $page++) {
            $response = Payload::of($this->client->getJson('francetravail', (string) $options['url'], [
                'motsCles' => 'télétravail',
                'minCreationDate' => now()->subDays((int) $this->config->get('jobs.max_age_days'))->utc()->format('Y-m-d\TH:i:s\Z'),
                'range' => ($page * self::PAGE).'-'.(($page + 1) * self::PAGE - 1),
            ], ['Authorization' => 'Bearer '.$token]));

            $offers = $response->array('resultats');
            foreach ($offers as $offer) {
                if (is_array($offer) && isset($offer['id'])) {
                    yield new RawJob('francetravail', (string) $offer['id'], $offer);
                }
            }

            if (count($offers) < self::PAGE) {
                return;
            }
        }
    }
}
