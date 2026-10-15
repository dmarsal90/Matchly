<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Http\Controllers;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Application\Actions\DeleteCv;
use App\Modules\Profiles\Application\Actions\SetPrimaryCv;
use App\Modules\Profiles\Application\Actions\UploadCv;
use App\Modules\Profiles\Http\Requests\UploadCvRequest;
use App\Modules\Profiles\Infrastructure\Models\CvDocument;
use App\Modules\Shared\Infrastructure\Files\EncryptedFileStore;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CvDocumentController
{
    public function store(UploadCvRequest $request, #[CurrentUser] User $user, UploadCv $upload): RedirectResponse
    {
        $file = $request->cv();

        $upload->handle($user, (string) $file->get(), $file->getClientOriginalName(), $request->detectedMimeType());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('profile.cv.uploaded')]);

        return back();
    }

    public function download(CvDocument $cv, EncryptedFileStore $files): StreamedResponse
    {
        Gate::authorize('view', $cv);

        $contents = $files->get($cv->path);

        return response()->streamDownload(
            function () use ($contents): void {
                echo $contents;
            },
            $cv->original_name,
            ['Content-Type' => $cv->mime_type, 'Cache-Control' => 'private, no-store'],
        );
    }

    public function primary(CvDocument $cv, SetPrimaryCv $setPrimary): RedirectResponse
    {
        Gate::authorize('update', $cv);

        $setPrimary->handle($cv);

        return back();
    }

    public function destroy(CvDocument $cv, DeleteCv $delete): RedirectResponse
    {
        Gate::authorize('delete', $cv);

        $delete->handle($cv);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('profile.cv.deleted')]);

        return back();
    }
}
