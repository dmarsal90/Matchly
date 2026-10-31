<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\GoogleJobs;

use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Infrastructure\Sources\JSearch\JSearchProvider;

/**
 * OpenWeb Ninja's Google Jobs endpoint: same job shape as JSearch, flat result list.
 */
final class GoogleJobsProvider extends JSearchProvider
{
    protected function key(): string
    {
        return 'googlejobs';
    }

    protected function url(): string
    {
        return 'https://api.openwebninja.com/realtime-jobs-data/google-jobs/search';
    }

    protected function jobsPath(): string
    {
        return 'data';
    }

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'googlejobs',
            name: 'Google Jobs',
            homepageUrl: 'https://www.openwebninja.com',
            termsUrl: 'https://www.openwebninja.com/terms',
        );
    }
}
