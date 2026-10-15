<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Actions;

use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Domain\Enums\LetterLength;
use App\Modules\Applications\Domain\Enums\LetterTone;
use App\Modules\Applications\Domain\Enums\PackageStatus;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Applications\Infrastructure\Models\ApplicationPackage;
use App\Modules\Applications\Infrastructure\Queue\GeneratePackageJob;
use App\Modules\Identity\Application\Actions\GrantConsent;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use Carbon\CarbonImmutable;

/**
 * One click: a package (letter + tailored CV + contact data) starts being
 * prepared in the background and the job moves to "prepared" in the
 * person's tracking. Nothing is sent to the employer.
 */
final readonly class StartQuickApply
{
    public function __construct(private GrantConsent $grantConsent, private RecordApplicationEvent $record) {}

    public function handle(User $user, JobListing $job, LetterTone $tone, LetterLength $length, bool $useAi): ApplicationPackage
    {
        if ($useAi) {
            // The checkbox in the dialog is the explicit consent to send profile data to an AI provider.
            $this->grantConsent->handle($user, ConsentPurpose::AiProcessing);
        }

        $application = Application::query()->where('user_id', $user->id)->where('job_listing_id', $job->id)->first() ?? new Application;
        if (! $application->exists || $application->status === ApplicationStatus::Saved) {
            $application->forceFill([
                'user_id' => $user->id,
                'job_listing_id' => $job->id,
                'status' => ApplicationStatus::Prepared,
                'status_changed_at' => CarbonImmutable::now(),
            ])->save();
            $this->record->statusChanged($application, ApplicationStatus::Prepared, $application->status_changed_at);
        }

        $package = new ApplicationPackage([
            'status' => PackageStatus::Generating,
            'language' => app()->getLocale(),
            'tone' => $tone,
            'length' => $length,
        ]);
        $package->forceFill(['user_id' => $user->id, 'job_listing_id' => $job->id])->save();

        GeneratePackageJob::dispatch($package->id, $useAi);

        return $package;
    }
}
