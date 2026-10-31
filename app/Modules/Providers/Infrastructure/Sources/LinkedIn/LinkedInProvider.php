<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\LinkedIn;

use App\Modules\Providers\Domain\Data\ProviderDefinition;
use App\Modules\Providers\Infrastructure\Sources\JSearch\JSearchProvider;

/**
 * OpenWeb Ninja's LinkedIn jobs endpoint: same job shape as JSearch, flat result list.
 */
final class LinkedInProvider extends JSearchProvider
{
    protected function key(): string
    {
        return 'linkedin';
    }

    protected function url(): string
    {
        return 'https://api.openwebninja.com/realtime-linkedin-data/search';
    }

    protected function jobsPath(): string
    {
        return 'data';
    }

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            key: 'linkedin',
            name: 'LinkedIn',
            homepageUrl: 'https://www.linkedin.com/jobs',
            termsUrl: 'https://www.openwebninja.com/terms',
        );
    }
}
