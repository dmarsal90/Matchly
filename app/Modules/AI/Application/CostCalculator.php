<?php

declare(strict_types=1);

namespace App\Modules\AI\Application;

use App\Modules\AI\Domain\Data\AiResult;

/** USD cost of a call from config('ai.pricing'); unlisted models are free. */
final readonly class CostCalculator
{
    /**
     * @param  array<string, array{input: float, output: float, cache_read?: float, cache_write?: float}>  $pricing  USD per million tokens
     */
    public function __construct(private array $pricing) {}

    public function cost(string $model, AiResult $result): float
    {
        $price = $this->pricing[$model] ?? null;
        if ($price === null) {
            return 0.0;
        }

        $cost = $result->inputTokens * $price['input']
            + $result->outputTokens * $price['output']
            + $result->cacheReadTokens * ($price['cache_read'] ?? $price['input'])
            + $result->cacheWriteTokens * ($price['cache_write'] ?? $price['input']);

        return round($cost / 1_000_000, 6);
    }

    public function isFree(string $model): bool
    {
        return ! isset($this->pricing[$model]);
    }
}
