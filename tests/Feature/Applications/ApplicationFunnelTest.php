<?php

declare(strict_types=1);

use App\Modules\Applications\Application\Queries\ApplicationFunnel;
use App\Modules\Applications\Domain\Enums\ApplicationEventType;
use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use Inertia\Testing\AssertableInertia as Assert;

function trackedAt(User $user, ApplicationStatus $status, ?int $appliedDaysAgo, ?JobListing $job = null): Application
{
    $application = new Application;
    $application->forceFill([
        'user_id' => $user->id,
        'job_listing_id' => ($job ?? JobListing::factory()->create())->id,
        'status' => $status,
        'status_changed_at' => now(),
        'applied_at' => $appliedDaysAgo === null ? null : now()->subDays($appliedDaysAgo),
    ])->save();

    return $application;
}

it('counts how far applications got and the conversion between stages', function (): void {
    $user = User::factory()->create();
    trackedAt($user, ApplicationStatus::Saved, null);
    trackedAt($user, ApplicationStatus::Applied, 5);
    trackedAt($user, ApplicationStatus::NoResponse, 30);
    trackedAt($user, ApplicationStatus::Interview, 20);
    $rejectedAfterInterview = trackedAt($user, ApplicationStatus::Rejected, 25);
    $rejectedAfterInterview->events()->create(['type' => ApplicationEventType::Interview, 'round' => 1, 'occurred_at' => now()->subDays(15)]);
    trackedAt($user, ApplicationStatus::Offer, 40);
    trackedAt($user, ApplicationStatus::Accepted, 60);

    $funnel = app(ApplicationFunnel::class)->for($user->id);

    expect($funnel['applied'])->toBe(6)
        ->and(array_column($funnel['stages'], 'count', 'key'))->toBe(['applied' => 6, 'interview' => 4, 'offer' => 2, 'accepted' => 1])
        ->and(array_column($funnel['conversion'], 'rate', 'to'))->toBe(['interview' => 67, 'offer' => 50, 'accepted' => 50])
        ->and($funnel['outcomes'])->toMatchArray(['waiting' => 1, 'interview' => 1, 'offer' => 1, 'accepted' => 1, 'rejected' => 1, 'no_response' => 1]);
});

it('measures how long employers take to answer', function (): void {
    $user = User::factory()->create();
    $one = trackedAt($user, ApplicationStatus::Interview, 20);
    $one->events()->create(['type' => ApplicationEventType::Interview, 'round' => 1, 'occurred_at' => now()->subDays(14)]);
    $two = trackedAt($user, ApplicationStatus::Rejected, 20);
    $two->events()->create(['type' => ApplicationEventType::StatusChanged, 'status' => ApplicationStatus::Rejected, 'occurred_at' => now()->subDays(10)]);
    trackedAt($user, ApplicationStatus::Applied, 3);

    $timing = app(ApplicationFunnel::class)->for($user->id)['timing'];

    // Answers after 6 and 10 days; only one of them was an interview.
    expect($timing['first_response_days'])->toBe(8.0)
        ->and($timing['first_interview_days'])->toBe(6.0);
});

it('groups by source and area and never counts other people’s applications', function (): void {
    $user = User::factory()->create();
    $job = JobListing::factory()->create();
    trackedAt($user, ApplicationStatus::Interview, 10, $job);
    trackedAt($user, ApplicationStatus::Applied, 4, JobListing::factory()->create(['source_id' => $job->source_id]));
    trackedAt(User::factory()->create(), ApplicationStatus::Offer, 4);

    $funnel = app(ApplicationFunnel::class)->for($user->id);

    expect($funnel['applied'])->toBe(2)
        ->and($funnel['by_source'])->toHaveCount(1)
        ->and($funnel['by_source'][0])->toMatchArray(['name' => $job->source->name, 'applied' => 2, 'interviews' => 1, 'offers' => 0]);
});

it('returns an empty funnel without applications', function (): void {
    $funnel = app(ApplicationFunnel::class)->for(User::factory()->create()->id);

    expect($funnel['applied'])->toBe(0)
        ->and(array_column($funnel['conversion'], 'rate'))->toBe([null, null, null])
        ->and($funnel['timing']['first_response_days'])->toBeNull();
});

it('sends the funnel to the insights page', function (): void {
    $user = User::factory()->create();
    trackedAt($user, ApplicationStatus::Applied, 4);

    $this->actingAs($user)->get('/insights')->assertInertia(fn (Assert $page) => $page->where('funnel.applied', 1));
});

it('compares the match score of jobs that got an interview with the ones that did not', function (): void {
    $user = User::factory()->create();
    $score = function (Application $application, int $score) use ($user): void {
        App\Modules\Matching\Infrastructure\Models\JobMatch::query()->create(['user_id' => $user->id, 'job_listing_id' => $application->job_listing_id, 'score' => $score, 'level' => 'medium', 'eligible' => true, 'result' => [], 'computed_at' => now()]);
    };
    $score(trackedAt($user, ApplicationStatus::Interview, 20), 80);
    $score(trackedAt($user, ApplicationStatus::Offer, 30), 70);
    $score(trackedAt($user, ApplicationStatus::Rejected, 25), 50);
    $score(trackedAt($user, ApplicationStatus::NoResponse, 40), 40);
    // Still waiting: tells nothing yet.
    $score(trackedAt($user, ApplicationStatus::Applied, 3), 10);

    expect(app(ApplicationFunnel::class)->for($user->id)['calibration'])->toBe(['interview_score' => 75, 'closed_score' => 45, 'interview_count' => 2, 'closed_count' => 2])
        ->and(app(ApplicationFunnel::class)->for(User::factory()->create()->id)['calibration']['interview_score'])->toBeNull();
});
