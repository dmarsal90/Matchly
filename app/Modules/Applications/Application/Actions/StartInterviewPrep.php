<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Actions;

use App\Modules\Applications\Domain\Enums\InterviewPrepStatus;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Applications\Infrastructure\Models\InterviewPrep;
use App\Modules\Applications\Infrastructure\Queue\GenerateInterviewPrepJob;
use App\Modules\Identity\Application\Actions\GrantConsent;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;

/** (Re)starts preparing the interview pack of an application in the background. */
final readonly class StartInterviewPrep
{
    public function __construct(private GrantConsent $grantConsent) {}

    public function handle(Application $application, bool $useAi): InterviewPrep
    {
        if ($useAi && $application->user !== null) {
            // The checkbox is the explicit consent to send profile data to an AI provider.
            $this->grantConsent->handle($application->user, ConsentPurpose::AiProcessing);
        }

        $prep = InterviewPrep::query()->where('application_id', $application->id)->first() ?? new InterviewPrep;
        $prep->application()->associate($application);
        $prep->fill(['status' => InterviewPrepStatus::Generating, 'language' => app()->getLocale(), 'source' => null, 'content' => null, 'warnings' => null])->save();

        GenerateInterviewPrepJob::dispatch($prep->id, $useAi);

        return $prep;
    }
}
