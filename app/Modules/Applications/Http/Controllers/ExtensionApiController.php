<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Controllers;

use App\Modules\Applications\Application\Generators\FormAnswerWriter;
use App\Modules\Applications\Domain\Contracts\ApplicationAssistant;
use App\Modules\Applications\Domain\Enums\PackageStatus;
use App\Modules\Applications\Domain\Enums\ResumeTemplate;
use App\Modules\Applications\Infrastructure\Models\ApplicationPackage;
use App\Modules\Applications\Infrastructure\Pdf\ResumePdfRenderer;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Matching\Application\Actions\ResolveJobRequirements;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * What the browser extension can read: the applications the person approved,
 * the form fields taken from them and the CV PDF, plus a draft answer to an
 * open question of the form. It stores nothing and never sees an application
 * that is still a draft.
 */
final class ExtensionApiController
{
    private const int MAX_PACKAGES = 30;

    public function index(#[CurrentUser] User $user): JsonResponse
    {
        $approved = ApplicationPackage::query()
            ->with('job:id,title,company,apply_url')
            ->where('user_id', $user->id)
            ->where('status', PackageStatus::Approved)
            ->latest('approved_at')
            ->limit(self::MAX_PACKAGES)
            ->get()
            ->unique('job_listing_id');

        $packages = [];
        foreach ($approved as $package) {
            $job = $package->job;
            if ($job !== null) {
                $packages[] = ['id' => $package->id, 'title' => $job->title, 'company' => $job->company, 'apply_url' => $job->apply_url, 'approved_at' => $package->approved_at?->toIso8601String()];
            }
        }

        return response()->json(['packages' => $packages])->header('Cache-Control', 'private, no-store');
    }

    public function fields(ApplicationPackage $package, #[CurrentUser] User $user, ApplicationAssistant $assistant): JsonResponse
    {
        $this->authorizeApproved($package, $user);

        return response()->json(['fields' => $assistant->fieldsFor($package)])->header('Cache-Control', 'private, no-store');
    }

    /** A draft for an open question; AI only when the person already consented to it in the app. */
    public function answer(Request $request, ApplicationPackage $package, #[CurrentUser] User $user, FormAnswerWriter $writer, ResolveJobRequirements $requirements): JsonResponse
    {
        $this->authorizeApproved($package, $user);
        $question = $request->validate(['question' => ['required', 'string', 'min:5', 'max:500']])['question'];
        $resume = $package->resumeData();
        $job = $package->job;
        abort_if($resume === null || $job === null, 404);

        $draft = $writer->write($user, $resume, $job, $requirements->handle($job), $question, $package->language, $user->hasConsented(ConsentPurpose::AiProcessing));

        return response()->json(['answer' => $draft['answer'], 'source' => $draft['source']->value, 'warnings' => $draft['warnings']])->header('Cache-Control', 'private, no-store');
    }

    public function resume(Request $request, ApplicationPackage $package, #[CurrentUser] User $user, ResumePdfRenderer $renderer): Response
    {
        $this->authorizeApproved($package, $user);
        $resume = $package->resumeData();
        abort_if($resume === null, 404);

        return response($renderer->render($resume, $package->language, ResumeTemplate::tryFrom($request->string('template')->toString()) ?? ResumeTemplate::Classic), 200, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function authorizeApproved(ApplicationPackage $package, User $user): void
    {
        // A missing or foreign package looks the same: 404.
        abort_unless($package->user_id === $user->id && $package->status === PackageStatus::Approved, 404);
    }
}
