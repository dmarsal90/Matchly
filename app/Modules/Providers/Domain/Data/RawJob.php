<?php

declare(strict_types=1);

namespace App\Modules\Providers\Domain\Data;

/**
 * One posting exactly as a source returned it. Only the source's normalizer
 * knows how to read the payload.
 */
final readonly class RawJob
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $source,
        public string $externalId,
        public array $payload,
    ) {}
}
