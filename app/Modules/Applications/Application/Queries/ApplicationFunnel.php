<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Queries;

use App\Modules\Applications\Domain\Enums\ApplicationEventType;
use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Applications\Infrastructure\Models\ApplicationEvent;
use Carbon\CarbonImmutable;

/**
 * How the person's applications have gone: how far each one got, the
 * conversion between stages, how long employers took to answer and which
 * sources and areas worked best. Computed from the applications and their
 * history; applications without history fall back to their current status.
 */
final readonly class ApplicationFunnel
{
    private const array STAGES = ['applied' => 1, 'interview' => 2, 'offer' => 3, 'accepted' => 4];

    private const int MAX_GROUPS = 8;

    /**
     * @return array{
     *     applied: int,
     *     stages: list<array{key: string, count: int}>,
     *     conversion: list<array{from: string, to: string, rate: int|null}>,
     *     outcomes: array<string, int>,
     *     timing: array{first_response_days: float|null, first_interview_days: float|null},
     *     by_source: list<array{name: string, applied: int, interviews: int, offers: int}>,
     *     by_category: list<array{name: string, applied: int, interviews: int, offers: int}>
     * }
     */
    public function for(int $userId): array
    {
        $applications = Application::query()->where('user_id', $userId)->with(['events', 'job.source:id,name'])->get();

        $counts = array_fill_keys(array_keys(self::STAGES), 0);
        /** @var array<string, int> $outcomes */
        $outcomes = ['waiting' => 0, 'interview' => 0, 'offer' => 0, 'accepted' => 0, 'declined' => 0, 'rejected' => 0, 'no_response' => 0];
        $firstResponse = [];
        $firstInterview = [];
        /** @var array<string, array{name: string, applied: int, interviews: int, offers: int}> $bySource */
        $bySource = [];
        /** @var array<string, array{name: string, applied: int, interviews: int, offers: int}> $byCategory */
        $byCategory = [];
        $applied = 0;

        foreach ($applications as $application) {
            $rank = $this->rank($application);
            if ($rank < 1) {
                continue;
            }

            $applied++;
            foreach (self::STAGES as $key => $stageRank) {
                $counts[$key] += $rank >= $stageRank ? 1 : 0;
            }
            $outcome = $application->status === ApplicationStatus::Applied ? 'waiting' : $application->status->value;
            $outcomes[$outcome] = ($outcomes[$outcome] ?? 0) + 1;

            if ($application->applied_at !== null) {
                $firstResponse[] = $this->daysUntil($application, fn (ApplicationEvent $event): bool => $this->isResponse($event));
                $firstInterview[] = $this->daysUntil($application, fn (ApplicationEvent $event): bool => $this->isInterview($event));
            }

            $this->tally($bySource, $application->job->source->name, $rank);
            $this->tally($byCategory, (string) ($application->job->category->value ?? 'other'), $rank);
        }

        $keys = array_keys(self::STAGES);
        $conversion = [];
        foreach ($keys as $index => $key) {
            if ($index > 0) {
                $from = $keys[$index - 1];
                $conversion[] = ['from' => $from, 'to' => $key, 'rate' => $counts[$from] > 0 ? (int) round(100 * $counts[$key] / $counts[$from]) : null];
            }
        }

        return [
            'applied' => $applied,
            'stages' => array_map(fn (string $key): array => ['key' => $key, 'count' => $counts[$key]], $keys),
            'conversion' => $conversion,
            'outcomes' => $outcomes,
            'timing' => ['first_response_days' => $this->average($firstResponse), 'first_interview_days' => $this->average($firstInterview)],
            'by_source' => $this->top($bySource),
            'by_category' => $this->top($byCategory),
        ];
    }

    /**
     * @param  array<string, array{name: string, applied: int, interviews: int, offers: int}>  $groups
     */
    private function tally(array &$groups, string $name, int $rank): void
    {
        $group = $groups[$name] ?? ['name' => $name, 'applied' => 0, 'interviews' => 0, 'offers' => 0];
        $group['applied']++;
        $group['interviews'] += $rank >= 2 ? 1 : 0;
        $group['offers'] += $rank >= 3 ? 1 : 0;
        $groups[$name] = $group;
    }

    /** How far the application got: 0 not applied, 1 applied, 2 interview, 3 offer, 4 accepted. */
    private function rank(Application $application): int
    {
        $rank = $application->applied_at !== null ? 1 : 0;
        $rank = max($rank, match ($application->status) {
            ApplicationStatus::Applied => 1,
            ApplicationStatus::Interview => 2,
            ApplicationStatus::Offer, ApplicationStatus::Declined => 3,
            ApplicationStatus::Accepted => 4,
            default => 0,
        });
        foreach ($application->events as $event) {
            $rank = max($rank, $this->isInterview($event) ? 2 : 0, $event->status !== null ? (self::STAGES[$event->status->value] ?? ($event->status === ApplicationStatus::Declined ? 3 : 0)) : 0);
        }

        return $rank;
    }

    private function isInterview(ApplicationEvent $event): bool
    {
        return $event->type === ApplicationEventType::Interview || ($event->type === ApplicationEventType::StatusChanged && $event->status === ApplicationStatus::Interview);
    }

    /** The employer answered: an interview, an offer or a rejection. */
    private function isResponse(ApplicationEvent $event): bool
    {
        return $this->isInterview($event) || ($event->type === ApplicationEventType::StatusChanged && in_array($event->status, [ApplicationStatus::Offer, ApplicationStatus::Rejected, ApplicationStatus::Accepted, ApplicationStatus::Declined], true));
    }

    /**
     * @param  callable(ApplicationEvent): bool  $matches
     */
    private function daysUntil(Application $application, callable $matches): ?float
    {
        /** @var CarbonImmutable $appliedAt */
        $appliedAt = $application->applied_at;
        $first = $application->events->filter(fn (ApplicationEvent $event): bool => $matches($event) && $event->occurred_at >= $appliedAt)->min('occurred_at');

        return $first === null ? null : round($appliedAt->diffInSeconds($first) / 86400, 1);
    }

    /**
     * @param  list<float|null>  $values
     */
    private function average(array $values): ?float
    {
        $values = array_values(array_filter($values, fn (?float $value): bool => $value !== null));

        return $values === [] ? null : round(array_sum($values) / count($values), 1);
    }

    /**
     * @param  array<string, array{name: string, applied: int, interviews: int, offers: int}>  $groups
     * @return list<array{name: string, applied: int, interviews: int, offers: int}>
     */
    private function top(array $groups): array
    {
        $groups = array_values($groups);
        usort($groups, fn (array $a, array $b): int => [$b['applied'], $a['name']] <=> [$a['applied'], $b['name']]);

        return array_slice($groups, 0, self::MAX_GROUPS);
    }
}
