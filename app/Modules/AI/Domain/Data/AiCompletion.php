<?php

declare(strict_types=1);

namespace App\Modules\AI\Domain\Data;

/** One request to one provider. */
final readonly class AiCompletion
{
    /**
     * @param  array<string, mixed>  $schema  JSON Schema the answer must follow
     */
    public function __construct(
        public string $system,
        public string $input,
        public array $schema,
        public string $model,
        public int $maxOutputTokens,
        public ?string $apiKey = null,
    ) {}
}
