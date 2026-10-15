<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Ats\Greenhouse;

use App\Modules\Providers\Domain\Data\AtsCompanyRef;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Infrastructure\Sources\Support\AbstractAtsProvider;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;

final class GreenhouseProvider extends AbstractAtsProvider
{
    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'greenhouse',
            name: 'Greenhouse',
            homepageUrl: 'https://www.greenhouse.com',
            termsUrl: 'https://developers.greenhouse.io/job-board.html',
            requiresAttribution: false,
        );
    }

    protected function fetchCompany(AtsCompanyRef $company): iterable
    {
        /** @var list<array<string, mixed>> */
        return array_values(array_filter(
            Payload::of($this->client->getJson('greenhouse', $this->url('url', $company)))->array('jobs'),
            'is_array',
        ));
    }

    protected function externalId(array $posting): ?string
    {
        return Payload::of($posting)->string('id');
    }
}
