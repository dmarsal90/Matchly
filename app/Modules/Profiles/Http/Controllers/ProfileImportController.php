<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Http\Controllers;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Application\Actions\AnalyzeCvSkills;
use App\Modules\Profiles\Application\Actions\ConfirmImport;
use App\Modules\Profiles\Application\Actions\ImportLinkedInExport;
use App\Modules\Profiles\Application\Actions\StartGithubImport;
use App\Modules\Profiles\Domain\Enums\ImportStatus;
use App\Modules\Profiles\Domain\Enums\LanguageLevel;
use App\Modules\Profiles\Domain\Exceptions\ImportFailed;
use App\Modules\Profiles\Http\Requests\AnalyzeCvRequest;
use App\Modules\Profiles\Http\Requests\ConfirmImportRequest;
use App\Modules\Profiles\Http\Requests\StartGithubImportRequest;
use App\Modules\Profiles\Http\Requests\UploadLinkedInExportRequest;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Starting imports from each source, reviewing them and confirming or discarding them. */
final class ProfileImportController
{
    public function github(StartGithubImportRequest $request, #[CurrentUser] User $user, StartGithubImport $start): RedirectResponse
    {
        $start->handle($user, $request->username(), $request->boolean('ai_summary'));

        Inertia::flash('toast', ['type' => 'info', 'message' => __('skills.github.started')]);

        return back();
    }

    public function linkedin(UploadLinkedInExportRequest $request, #[CurrentUser] User $user, ImportLinkedInExport $import): RedirectResponse
    {
        try {
            $result = $import->handle($user, (string) $request->archive()->getRealPath());
        } catch (ImportFailed $exception) {
            throw ValidationException::withMessages(['file' => __('skills.errors.'.$exception->reason)]);
        }

        return to_route('skills.imports.show', $result);
    }

    public function cv(AnalyzeCvRequest $request, #[CurrentUser] User $user, AnalyzeCvSkills $analyze): RedirectResponse
    {
        try {
            $result = $analyze->handle($user, $request->boolean('use_ai'));
        } catch (ImportFailed $exception) {
            throw ValidationException::withMessages(['cv' => __('skills.errors.'.$exception->reason)]);
        }

        if ($result->status === ImportStatus::Processing) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('skills.sources.cv.started')]);

            return back();
        }

        return to_route('skills.imports.show', $result);
    }

    public function show(ProfileImport $import): Response|RedirectResponse
    {
        Gate::authorize('view', $import);

        if ($import->status !== ImportStatus::PendingReview) {
            return to_route('skills.index');
        }

        return Inertia::render('skills/ReviewImport', [
            'review' => [
                'id' => $import->id,
                'source' => $import->source->value,
                'reference' => $import->reference,
                'created_at' => $import->created_at->toIso8601String(),
                'data' => $import->payload ?? [],
            ],
            'currentHeadline' => $import->user?->profile?->headline,
            'languageLevels' => array_column(LanguageLevel::cases(), 'value'),
        ]);
    }

    public function confirm(ConfirmImportRequest $request, ProfileImport $import, ConfirmImport $confirm): RedirectResponse
    {
        Gate::authorize('update', $import);

        $confirm->handle($import, $request->selection());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('skills.import.confirmed')]);

        return to_route('skills.index');
    }

    public function destroy(ProfileImport $import): RedirectResponse
    {
        Gate::authorize('delete', $import);

        // Discarding is for imports in flight; confirmed data is deleted per source.
        abort_if($import->status === ImportStatus::Confirmed, 409);

        $import->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('skills.import.discarded')]);

        return to_route('skills.index');
    }
}
