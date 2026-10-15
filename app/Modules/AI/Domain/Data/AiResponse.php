<?php

declare(strict_types=1);

namespace App\Modules\AI\Domain\Data;

/** A validated answer, as returned to use cases. */
final readonly class AiResponse
{
    /**
     * @param  array<string, mixed>  $data  matches the task's JSON schema
     */
    public function __construct(
        public array $data,
        public string $provider,
        public string $model,
        public float $costUsd,
    ) {}
}
