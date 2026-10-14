<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Controllers;

use App\Modules\Applications\Application\Actions\UpdateApplication;
use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Http\Requests\UpdateApplicationRequest;
use App\Modules\Applications\Infrastructure\Models\Application;
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
    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        $applications = Application::query()
            ->where('user_id', $user->id)
            // Defence in depth: never surface jobs held for moderation.
            ->whereHas('job', fn ($job) => $job->where('status', '!=', JobStatus::Flagged->value))
            ->with('job.source:id,key,name,homepage_url,requires_attribution')
            ->latest('status_changed_at')
            ->get();

        return Inertia::render('applications/Index', [
            'applications' => $applications->map(fn (Application $application): array => [
                'id' => $application->id,
                'status' => $application->status->value,
                'notes' => $application->notes,
                'status_changed_at' => $application->status_changed_at->toIso8601String(),
                'applied_at' => $application->applied_at?->toIso8601String(),
                'job_open' => $application->job->status === JobStatus::Open,
                'job' => (new JobListingResource($application->job))->resolve($request),
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
