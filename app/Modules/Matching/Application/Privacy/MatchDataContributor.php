<?php

declare(strict_types=1);

namespace App\Modules\Matching\Application\Privacy;

use App\Modules\Identity\Domain\Contracts\PersonalDataContributor;
use App\Modules\Identity\Domain\Data\PersonalData;
use App\Modules\Matching\Infrastructure\Models\JobMatch;

/** Compatibility scores are automated profiling (GDPR art. 15.1.h): exported with their explanation. */
final readonly class MatchDataContributor implements PersonalDataContributor
{
    public function export(int $userId): PersonalData
    {
        return new PersonalData('matches', [
            'scores' => JobMatch::query()->where('user_id', $userId)->with('job:id,title,company')->orderByDesc('score')->get()
                ->map(fn (JobMatch $match): array => [
                    'job' => $match->job !== null ? "{$match->job->title} · {$match->job->company}" : $match->job_listing_id,
                    'score' => $match->score,
                    'level' => $match->level,
                    'eligible' => $match->eligible,
                    'explanation' => $match->result,
                    'computed_at' => $match->computed_at->toIso8601String(),
                ])->values()->all(),
        ]);
    }

    public function erase(int $userId): void
    {
        // Rows cascade with the account.
    }
}
