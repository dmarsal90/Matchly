<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Controllers;

use App\Modules\Applications\Application\Actions\ApprovePackage;
use App\Modules\Applications\Application\Actions\MarkApplied;
use App\Modules\Applications\Application\Actions\StartQuickApply;
use App\Modules\Applications\Application\Actions\UpdatePackage;
use App\Modules\Applications\Domain\Enums\PackageStatus;
use App\Modules\Applications\Domain\Enums\ResumeTemplate;
use App\Modules\Applications\Http\Requests\StartQuickApplyRequest;
use App\Modules\Applications\Http\Requests\UpdatePackageRequest;
use App\Modules\Applications\Http\Resources\PackageResource;
use App\Modules\Applications\Infrastructure\Models\ApplicationPackage;
use App\Modules\Applications\Infrastructure\Pdf\ResumePdfRenderer;
use App\Modules\Applications\Infrastructure\Queue\GeneratePackageJob;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Domain\Enums\JobStatus;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Matching\Infrastructure\Models\JobMatch;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** Quick apply: prepare, review, approve and export an application package. Nothing is sent for the user. */
final class QuickApplyController
{
    public function start(StartQuickApplyRequest $request, JobListing $job, #[CurrentUser] User $user, StartQuickApply $start): RedirectResponse
    {
        abort_if($job->status === JobStatus::Flagged && ! $user->is_admin, 404);
        abort_if($job->status !== JobStatus::Open && $job->status !== JobStatus::Flagged, 410);

        $package = $start->handle($user, $job, $request->tone(), $request->length(), $request->boolean('use_ai'));

        Inertia::flash('toast', ['type' => 'info', 'message' => __('quick_apply.started')]);

        return to_route('applications.packages.show', $package);
    }

    public function show(ApplicationPackage $package): Response
    {
        Gate::authorize('view', $package);

        $job = $package->job()->firstOrFail();
        $match = JobMatch::query()->where('user_id', $package->user_id)->where('job_listing_id', $job->id)->first();

        return Inertia::render('applications/Package', [
            'package' => (new PackageResource($package))->resolve(),
            'job' => [
                'id' => $job->id,
                'slug' => Str::slug(Str::limit($job->title.' '.$job->company, 80, '')),
                'title' => $job->title,
                'company' => $job->company,
                'apply_url' => $job->apply_url,
                'is_open' => $job->status === JobStatus::Open,
            ],
            'missingKeywords' => array_values(array_map(
                fn (array $skill): string => (string) $skill['name'],
                array_filter((array) ($match->result['missing_skills'] ?? []), fn (mixed $skill): bool => is_array($skill) && ($skill['required'] ?? false)),
            )),
            'history' => ApplicationPackage::query()
                ->where('user_id', $package->user_id)
                ->where('job_listing_id', $job->id)
                ->whereKeyNot($package->id)
                ->latest('id')
                ->get(['id', 'status', 'created_at'])
                ->map(fn (ApplicationPackage $item): array => ['id' => $item->id, 'status' => $item->status->value, 'created_at' => $item->created_at->toIso8601String()])
                ->all(),
        ]);
    }

    public function update(UpdatePackageRequest $request, ApplicationPackage $package, UpdatePackage $update): RedirectResponse
    {
        Gate::authorize('update', $package);

        /** @var array<string, mixed>|null $resume */
        $resume = $request->validated('resume');
        $update->handle($package, $request->validated('cover_letter'), $resume !== null ? [...(array) $package->resume, ...$resume] : null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('quick_apply.package.saved')]);

        return back();
    }

    public function approve(ApplicationPackage $package, ApprovePackage $approve): RedirectResponse
    {
        Gate::authorize('update', $package);

        $approve->handle($package);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('quick_apply.package.approved')]);

        return back();
    }

    public function applied(ApplicationPackage $package, MarkApplied $markApplied): RedirectResponse
    {
        Gate::authorize('update', $package);

        $markApplied->handle($package);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('quick_apply.package.marked_applied')]);

        return back();
    }

    public function retry(ApplicationPackage $package): RedirectResponse
    {
        Gate::authorize('update', $package);
        abort_unless($package->status === PackageStatus::Failed, 409);

        $package->fill(['status' => PackageStatus::Generating, 'error' => null])->save();
        GeneratePackageJob::dispatch($package->id, (bool) $package->user?->hasConsented(ConsentPurpose::AiProcessing));

        return back();
    }

    public function letter(ApplicationPackage $package): HttpResponse
    {
        Gate::authorize('view', $package);
        abort_if($package->cover_letter === null, 404);

        return response((string) $package->cover_letter, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($package, 'txt').'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function resume(Request $request, ApplicationPackage $package, ResumePdfRenderer $renderer): HttpResponse
    {
        Gate::authorize('view', $package);
        // Only what the person approved is exported.
        abort_unless($package->status === PackageStatus::Approved && $package->resumeData() !== null, 403);

        return response($renderer->render($package->resumeData(), $package->language, ResumeTemplate::tryFrom($request->string('template')->toString()) ?? ResumeTemplate::Classic), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($package, 'pdf').'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** The CV as it would be exported, shown inline so it can be checked before approving. */
    public function resumePreview(Request $request, ApplicationPackage $package, ResumePdfRenderer $renderer): HttpResponse
    {
        Gate::authorize('view', $package);
        abort_unless($package->resumeData() !== null, 404);

        return response($renderer->render($package->resumeData(), $package->language, ResumeTemplate::tryFrom($request->string('template')->toString()) ?? ResumeTemplate::Classic), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->filename($package, 'pdf').'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroy(ApplicationPackage $package): RedirectResponse
    {
        Gate::authorize('delete', $package);

        $package->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('quick_apply.package.deleted')]);

        return to_route('applications.index');
    }

    private function filename(ApplicationPackage $package, string $extension): string
    {
        $name = Str::slug((string) ($package->resumeData()->contact['name'] ?? 'cv')) ?: 'cv';
        $company = Str::slug((string) $package->job?->company) ?: 'job';

        return "{$name}-{$company}.{$extension}";
    }
}
