<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Ats\SmartRecruiters;

use App\Modules\Providers\Domain\Data\AtsCompanyRef;
use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Infrastructure\Sources\Support\AbstractAtsProvider;
use App\Modules\Providers\Infrastructure\Sources\Support\Payload;

/**
 * The list endpoint has no descriptions; details are fetched only for remote
 * or hybrid postings to keep the request count low.
 */
final class SmartRecruitersProvider extends AbstractAtsProvider
{
    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'smartrecruiters',
            name: 'SmartRecruiters',
            homepageUrl: 'https://www.smartrecruiters.com',
            termsUrl: 'https://developers.smartrecruiters.com/docs/posting-api',
            requiresAttribution: false,
        );
    }

    protected function fetchCompany(AtsCompanyRef $company): iterable
    {
        $perPage = (int) $this->option('per_page') ?: 100;
        $maxPages = (int) $this->option('max_pages') ?: 5;

        for ($page = 0; $page < $maxPages; $page++) {
            $response = Payload::of($this->client->getJson('smartrecruiters', $this->url('list_url', $company), [
                'limit' => $perPage,
                'offset' => $page * $perPage,
            ]));

            $postings = $response->array('content');

            foreach ($postings as $posting) {
                if (! is_array($posting)) {
                    continue;
                }

                $summary = Payload::of($posting);
                if ($summary->bool('location.remote') !== true && $summary->bool('location.hybrid') !== true) {
                    continue;
                }

                $id = $summary->string('id');
                if ($id === null) {
                    continue;
                }

                $detail = $this->client->getJson('smartrecruiters', $this->url('detail_url', $company, ['{id}' => rawurlencode($id)]));

                yield [...$posting, ...$detail];
            }

            if (count($postings) < $perPage) {
                return;
            }
        }
    }

    protected function externalId(array $posting): ?string
    {
        return Payload::of($posting)->string('id');
    }
}
