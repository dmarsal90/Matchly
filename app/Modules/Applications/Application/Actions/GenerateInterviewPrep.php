<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Actions;

use App\Modules\Applications\Application\Generators\InterviewPrepBuilder;
use App\Modules\Applications\Application\ResumeBuilder;
use App\Modules\Applications\Domain\Enums\InterviewPrepStatus;
use App\Modules\Applications\Infrastructure\Models\InterviewPrep;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Matching\Application\Actions\ResolveJobRequirements;
use Psr\Log\LoggerInterface;
use Throwable;

/** Fills the pack of an application: the person reviews it, nothing leaves the app. */
final readonly class GenerateInterviewPrep
{
    public function __construct(
        private ResumeBuilder $resumes,
        private ResolveJobRequirements $requirements,
        private InterviewPrepBuilder $builder,
        private LoggerInterface $logger,
    ) {}

    public function handle(InterviewPrep $prep, bool $useAi): void
    {
        $application = $prep->application;
        $user = $application->user;
        $job = $application->job;
        if ($user === null || $prep->status !== InterviewPrepStatus::Generating) {
            return;
        }

        try {
            $requirements = $this->requirements->handle($job);
            $language = $requirements->language ?? ($user->locale ?? app()->getLocale());
            $resume = $this->resumes->for($user, $language);
            $pack = $this->builder->build($user, $resume, $job, $requirements, $language, $useAi && $user->hasConsented(ConsentPurpose::AiProcessing));

            $prep->fill([
                'status' => InterviewPrepStatus::Ready,
                'language' => $language,
                'source' => $pack['source'],
                'content' => ['questions' => $pack['questions'], 'gaps' => $pack['gaps'], 'ask' => $pack['ask']],
                'warnings' => $pack['warnings'],
            ])->save();
        } catch (Throwable $exception) {
            $this->logger->error('Interview prep failed', ['prep' => $prep->id, 'exception' => $exception]);
            $prep->fill(['status' => InterviewPrepStatus::Failed])->save();
        }
    }
}
