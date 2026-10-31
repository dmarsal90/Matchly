<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\BingJobs;

use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Infrastructure\Sources\JSearch\JSearchProvider;

/**
 * OpenWeb Ninja's Bing Jobs endpoint. shortcut: assumes the same flat job shape as the other
 * OpenWeb Ninja job endpoints; verify against a real response when the key is available.
 */
final class BingJobsProvider extends JSearchProvider
{
    protected function key(): string
    {
        return 'bingjobs';
    }

    protected function url(): string
    {
        return 'https://api.openwebninja.com/realtime-bing-jobs-data/search';
    }

    protected function jobsPath(): string
    {
        return 'data';
    }

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'bingjobs',
            name: 'Bing Jobs',
            homepageUrl: 'https://www.bing.com/jobs',
            termsUrl: 'https://www.openwebninja.com/terms',
        );
    }
}
