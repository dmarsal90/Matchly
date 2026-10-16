<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Actions;

use App\Modules\Applications\Application\Generators\AnswerFeedbackWriter;
use App\Modules\Applications\Application\ResumeBuilder;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Applications\Infrastructure\Models\InterviewAnswer;
use App\Modules\Identity\Application\Actions\GrantConsent;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;

/** Feedback on a practised answer, from plain checks or, if the person asks, from AI. */
final readonly class GiveAnswerFeedback
{
    public function __construct(private AnswerFeedbackWriter $writer, private ResumeBuilder $resumes, private GrantConsent $grantConsent) {}

    /**
     * @return array{answer: InterviewAnswer, aiError: string|null}|null null when there is no answer or question to comment on
     */
    public function handle(Application $application, string $key, bool $useAi): ?array
    {
        $application->loadMissing('user', 'interviewPrep');
        $user = $application->user;
        $question = SaveInterviewAnswer::questionFor($application, $key);
        $answer = InterviewAnswer::query()->where('application_id', $application->id)->where('question_key', $key)->first();
        if ($user === null || $question === null || $answer === null) {
            return null;
        }

        if ($useAi) {
            // The checkbox is the explicit consent to send the answer to an AI provider.
            $this->grantConsent->handle($user, ConsentPurpose::AiProcessing);
        }

        $language = $application->interviewPrep->language ?? app()->getLocale();
        $result = $this->writer->write($user, $question['question'], $question['skill'], $answer->answer, $language, $this->resumes->for($user, $language)->skills, $useAi);

        $answer->fill(['feedback' => $result['feedback'], 'feedback_source' => $result['source']])->save();

        return ['answer' => $answer, 'aiError' => $result['aiError']];
    }
}
