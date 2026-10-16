<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Actions;

use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Applications\Infrastructure\Models\InterviewAnswer;

/**
 * Keeps what the person wrote for one question of their interview pack. An
 * empty answer removes it; changing it drops the feedback, which was about the
 * old text.
 */
final readonly class SaveInterviewAnswer
{
    public const int MAX_LENGTH = 4000;

    public static function keyOf(string $question): string
    {
        // 40 hex characters: the length the column and the request rules expect.
        return substr(hash('sha256', $question), 0, 40);
    }

    /**
     * The question of the pack that a key points to, or null when the pack has no such question.
     *
     * @return array{category: string, question: string, skill: string|null}|null
     */
    public static function questionFor(Application $application, string $key): ?array
    {
        foreach ($application->interviewPrep?->content['questions'] ?? [] as $question) {
            if (self::keyOf($question['question']) === $key) {
                return ['category' => $question['category'], 'question' => $question['question'], 'skill' => $question['skill']];
            }
        }

        return null;
    }

    public function handle(Application $application, string $key, string $answer): ?InterviewAnswer
    {
        $answer = trim($answer);
        $existing = InterviewAnswer::query()->where('application_id', $application->id)->where('question_key', $key)->first();

        if ($answer === '') {
            $existing?->delete();

            return null;
        }

        if ($existing !== null && $existing->answer === $answer) {
            return $existing;
        }

        $stored = $existing ?? new InterviewAnswer;
        $stored->application()->associate($application);
        $stored->fill(['question_key' => $key, 'answer' => $answer, 'feedback' => null, 'feedback_source' => null])->save();

        return $stored;
    }
}
