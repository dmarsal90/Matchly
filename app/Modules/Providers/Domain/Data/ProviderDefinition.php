<?php

declare(strict_types=1);

namespace App\Modules\Providers\Domain\Data;

/**
 * Static facts about a source, shown for attribution next to every job it
 * provides, as most free job APIs require.
 */
final readonly class ProviderDefinition
{
    public function __construct(
        public string $key,
        public string $name,
        public string $homepageUrl,
        public ?string $termsUrl = null,
        /** Every posting is remote unless it says otherwise (remote-only boards). */
        public bool $remoteOnly = false,
        /** The source must be credited with a followable link to its own listing page. */
        public bool $requiresAttribution = true,
    ) {}
}
