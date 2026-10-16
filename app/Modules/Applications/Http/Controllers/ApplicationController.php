<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Controllers;

use App\Modules\Applications\Application\Actions\UpdateApplication;
use App\Modules\Applications\Application\Generators\FollowUpDraftWriter;
use App\Modules\Applications\Domain\Enums\ApplicationEventType;
use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Http\Requests\UpdateApplicationRequest;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Applications\Infrastructure\Models\ApplicationEvent;
use App\Modules\Applications\Infrastructure\Models\ApplicationPackage;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Domain\Enums\JobStatus;
use App\Modules\Jobs\Http\Resources\JobListingResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** "My jobs": the user's saved jobs and application pipeline. */
final class ApplicationController
{
    public function index(Request $request, #[CurrentUser] User $user, FollowUpDraftWriter $draftWriter): Response
    {
        $applications = Application::query()
            ->where('user_id', $user->id)
            // Defence in depth: never surface jobs held for moderation.
            ->whereHas('job', fn ($job) => $job->where('status', '!=', JobStatus::Flagged->value))
            ->with(['job.source:id,key,name,homepage_url,requires_attribution', 'events', 'user'])
            ->latest('status_changed_at')
            ->get();

        $packages = ApplicationPackage::query()
            ->where('user_id', $user->id)
            ->whereIn('job_listing_id', $applications->pluck('job_listing_id'))
            ->latest('id')
            ->get(['id', 'job_listing_id', 'status'])
            ->unique('job_listing_id')
            ->keyBy('job_listing_id');

        return Inertia::render('applications/Index', [
            'applications' => $applications->map(fn (Application $application): array => [
                'id' => $application->id,
                'status' => $application->status->value,
                'notes' => $application->notes,
                'status_changed_at' => $application->status_changed_at->toIso8601String(),
                'applied_at' => $application->applied_at?->toIso8601String(),
                'job_open' => $application->job->status === JobStatus::Open,
                'job' => (new JobListingResource($application->job))->resolve($request),
                'events' => $application->events->map(fn (ApplicationEvent $event): array => [
                    'id' => $event->id,
                    'type' => $event->type->value,
                    'status' => $event->status?->value,
                    'round' => $event->round,
                    'note' => $event->note,
                    'occurred_at' => $event->occurred_at->toIso8601String(),
                ])->values()->all(),
                // A message to copy for applications still waiting for an answer; never sent.
                'follow_up_draft' => $application->status === ApplicationStatus::Applied ? $draftWriter->write($application) : null,
                // After an interview round: a thank-you note to copy, never sent.
                'thank_you_draft' => $application->status === ApplicationStatus::Interview && $application->events->contains(fn (ApplicationEvent $event): bool => $event->type === ApplicationEventType::Interview) ? $draftWriter->thanks($application) : null,
                'package' => isset($packages[$application->job_listing_id])
                    ? ['id' => $packages[$application->job_listing_id]->id, 'status' => $packages[$application->job_listing_id]->status->value]
                    : null,
            ])->values()->all(),
            'statuses' => array_column(ApplicationStatus::cases(), 'value'),
        ]);
    }

    public function update(UpdateApplicationRequest $request, Application $application, UpdateApplication $update): RedirectResponse
    {
        Gate::authorize('update', $application);

        $update->handle($application, $request->status(), $request->notes());

        return back();
    }

    public function destroy(Application $application): RedirectResponse
    {
        Gate::authorize('delete', $application);

        $application->delete();

        return back();
    }
}
