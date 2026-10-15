<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Ats\Workable;

use App\Modules\Providers\Domain\Data\AtsCompanyRef;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Infrastructure\Sources\Support\AbstractAtsProvider;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;

/** Public careers widget endpoint (no API key); `details=true` includes descriptions. */
final class WorkableProvider extends AbstractAtsProvider
{
    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'workable',
            name: 'Workable',
            homepageUrl: 'https://www.workable.com',
            requiresAttribution: false,
        );
    }

    protected function fetchCompany(AtsCompanyRef $company): iterable
    {
        /** @var list<array<string, mixed>> */
        return array_values(array_filter(
            Payload::of($this->client->getJson('workable', $this->url('url', $company)))->array('jobs'),
            'is_array',
        ));
    }

    protected function externalId(array $posting): ?string
    {
        return Payload::of($posting)->string('shortcode');
    }
}
