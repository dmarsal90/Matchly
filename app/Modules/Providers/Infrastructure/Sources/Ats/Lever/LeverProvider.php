<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Ats\Lever;

use App\Modules\Providers\Domain\Data\AtsCompanyRef;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Infrastructure\Sources\Support\AbstractAtsProvider;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;

final class LeverProvider extends AbstractAtsProvider
{
    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'lever',
            name: 'Lever',
            homepageUrl: 'https://www.lever.co',
            termsUrl: 'https://github.com/lever/postings-api',
            requiresAttribution: false,
        );
    }

    protected function fetchCompany(AtsCompanyRef $company): iterable
    {
        /** @var list<array<string, mixed>> */
        return array_values(array_filter($this->client->getJson('lever', $this->url('url', $company)), 'is_array'));
    }

    protected function externalId(array $posting): ?string
    {
        return Payload::of($posting)->string('id');
    }
}
