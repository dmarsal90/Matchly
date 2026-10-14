<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\WeWorkRemotely;

use App\Modules\Providers\Domain\Contracts\JobProvider;
use App\Modules\Providers\Domain\Contracts\ReportsPartialFailures;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Providers\Infrastructure\Http\ProviderHttpClient;
use Illuminate\Contracts\Config\Repository as Config;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

/**
 * Public RSS feeds (main + categories). The same job appears in several
 * feeds, so items are de-duplicated by guid within a run.
 */
final class WeWorkRemotelyProvider implements JobProvider, ReportsPartialFailures
{
    private const string MEDIA_NAMESPACE = 'http://search.yahoo.com/mrss';

    /** @var list<string> */
    private array $failures = [];

    public function __construct(
        private readonly ProviderHttpClient $client,
        private readonly Config $config,
    ) {}

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'weworkremotely',
            name: 'We Work Remotely',
            homepageUrl: 'https://weworkremotely.com',
            termsUrl: 'https://weworkremotely.com/remote-job-rss-feed',
            remoteOnly: true,
        );
    }

    public function fetch(): iterable
    {
        $this->failures = [];
        $seen = [];

        /** @var list<string> $feeds */
        $feeds = (array) $this->config->get('jobs.providers.weworkremotely.options.feeds', []);

        foreach ($feeds as $feed) {
            try {
                $items = $this->parse($this->client->getText('weworkremotely', $feed));
            } catch (Throwable $exception) {
                $this->failures[] = "{$feed}: {$exception->getMessage()}";

                continue;
            }

            foreach ($items as $item) {
                $guid = $item['guid'] ?? $item['link'] ?? null;
                if (! is_string($guid) || isset($seen[$guid])) {
                    continue;
                }

                $seen[$guid] = true;
                yield new RawJob('weworkremotely', $guid, $item);
            }
        }
    }

    public function failures(): array
    {
        return $this->failures;
    }

    /**
     * @return list<array<string, string>>
     */
    private function parse(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);

        try {
            // LIBXML_NONET: never let a feed make us fetch external entities.
            $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($document === false || ! isset($document->channel->item)) {
            throw new RuntimeException('Invalid RSS document');
        }

        $items = [];
        foreach ($document->channel->item as $item) {
            $fields = [];
            foreach ($item->children() as $name => $value) {
                $fields[(string) $name] = trim((string) $value);
            }

            $media = $item->children(self::MEDIA_NAMESPACE)->content;
            if ($media !== null && isset($media->attributes()['url'])) {
                $fields['logo'] = (string) $media->attributes()['url'];
            }

            $items[] = $fields;
        }

        return $items;
    }
}
