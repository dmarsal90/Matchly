<?php

declare(strict_types=1);

namespace App\Modules\AI\Application\Privacy;

use App\Modules\AI\Infrastructure\Models\AiUsage;
use App\Modules\Identity\Domain\Contracts\PersonalDataContributor;
use App\Modules\Identity\Domain\Data\PersonalData;

/** AI calls made for the user (task, provider, tokens, cost); prompts and answers are not logged. */
final readonly class AiUsageDataContributor implements PersonalDataContributor
{
    public function export(int $userId): PersonalData
    {
        return new PersonalData('ai_usage', [
            'calls' => AiUsage::query()->where('user_id', $userId)->orderBy('id')->get()
                ->map(fn (AiUsage $usage): array => [
                    'at' => $usage->created_at->toIso8601String(),
                    'task' => $usage->task,
                    'provider' => $usage->provider,
                    'model' => $usage->model,
                    'own_key' => $usage->key_owner === AiUsage::USER_KEY,
                    'input_tokens' => $usage->input_tokens,
                    'output_tokens' => $usage->output_tokens,
                    'cost_usd' => $usage->cost_usd,
                    'success' => $usage->success,
                ])->values()->all(),
        ]);
    }

    public function erase(int $userId): void
    {
        // Usage rows are anonymised by the foreign key (user_id set to null) so spending totals stay correct.
    }
}
