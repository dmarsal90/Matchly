<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Actions;

use App\Modules\Applications\Application\Generators\CoverLetterWriter;
use App\Modules\Applications\Application\Generators\ResumeTailor;
use App\Modules\Applications\Application\ResumeBuilder;
use App\Modules\Applications\Domain\Enums\PackageStatus;
use App\Modules\Applications\Infrastructure\Models\ApplicationPackage;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Matching\Application\Actions\ResolveJobRequirements;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Builds the package contents: the master CV, its version tailored to the
 * job and the cover letter, in the posting's language. The result is a
 * draft: the person reviews and approves it before exporting anything.
 */
final readonly class GeneratePackage
{
    public function __construct(
        private ResumeBuilder $resumes,
        private ResolveJobRequirements $requirements,
        private ResumeTailor $tailor,
        private CoverLetterWriter $letters,
        private LoggerInterface $logger,
    ) {}

    public function handle(ApplicationPackage $package, bool $useAi): void
    {
        $user = $package->user;
        $job = $package->job;
        if ($user === null || $job === null || $package->status !== PackageStatus::Generating) {
            return;
        }

        try {
            $requirements = $this->requirements->handle($job);
            $language = $requirements->language ?? ($user->locale ?? app()->getLocale());
            $useAi = $useAi && $user->hasConsented(ConsentPurpose::AiProcessing);

            $master = $this->resumes->for($user, $language);
            $tailored = $this->tailor->tailor($user, $master, $job, $requirements, $language, $useAi);
            $letter = $this->letters->write($user, $tailored->resume, $job, $requirements, $language, $package->tone, $package->length, $user->profile?->years_experience, $useAi);

            $warnings = $letter->warnings;
            $aiError = $tailored->aiError ?? $letter->aiError;
            if ($aiError !== null) {
                $warnings[] = ['key' => 'ai_unavailable', 'params' => ['reason' => $aiError]];
            }
            if ($master->isEmpty()) {
                $warnings[] = ['key' => 'empty_profile', 'params' => []];
            }

            $package->fill([
                'status' => PackageStatus::Draft,
                'language' => $language,
                'master_resume' => $master->toArray(),
                'resume' => $tailored->resume->toArray(),
                'resume_source' => $tailored->source,
                'cover_letter' => $letter->text,
                'letter_source' => $letter->source,
                'warnings' => $warnings,
                'error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $this->logger->error('Quick apply package failed', ['package' => $package->id, 'exception' => $exception]);
            $package->fill(['status' => PackageStatus::Failed, 'error' => 'unexpected'])->save();
        }
    }
}
