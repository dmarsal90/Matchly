<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Ats\Ashby;

use App\Modules\Providers\Domain\Data\AtsCompanyRef;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Infrastructure\Sources\Support\AbstractAtsProvider;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;

final class AshbyProvider extends AbstractAtsProvider
{
    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'ashby',
            name: 'Ashby',
            homepageUrl: 'https://www.ashbyhq.com',
            termsUrl: 'https://developers.ashbyhq.com/docs/public-job-posting-api',
            requiresAttribution: false,
        );
    }

    protected function fetchCompany(AtsCompanyRef $company): iterable
    {
        $jobs = Payload::of($this->client->getJson('ashby', $this->url('url', $company)))->array('jobs');

        /** @var list<array<string, mixed>> */
        return array_values(array_filter($jobs, fn (mixed $job): bool => is_array($job) && ($job['isListed'] ?? true) !== false));
    }

    protected function externalId(array $posting): ?string
    {
        return Payload::of($posting)->string('id');
    }
}
