<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Generators;

use App\Modules\AI\Application\AiGateway;
use App\Modules\AI\Domain\Enums\AiTask;
use App\Modules\AI\Domain\Exceptions\AiUnavailable;
use App\Modules\Applications\Domain\Enums\ContentSource;
use App\Modules\Identity\Infrastructure\Models\User;
use Locale;

/**
 * Feedback on an answer the person practised. Without AI it is a handful of
 * plain checks (length, a concrete result, the skill being named); with AI it
 * is a short critique of how the answer is told. Either way it only comments
 * on what the person wrote: it never adds facts or rewrites the answer.
 *
 * @phpstan-type Note array{key?: string, text?: string}
 * @phpstan-type Feedback array{strengths: list<Note>, improvements: list<Note>}
 */
final readonly class AnswerFeedbackWriter
{
    private const int MIN_WORDS = 40;

    private const int MAX_WORDS = 250;

    public function __construct(private AiGateway $ai) {}

    /**
     * @param  list<string>  $candidateSkills
     * @return array{feedback: Feedback, source: ContentSource, aiError: string|null}
     */
    public function write(User $user, string $question, ?string $skill, string $answer, string $language, array $candidateSkills, bool $useAi): array
    {
        $rules = $this->checks($answer, $skill);
        if (! $useAi) {
            return ['feedback' => $rules, 'source' => ContentSource::Rules, 'aiError' => null];
        }

        try {
            $response = $this->ai->run(AiTask::InterviewFeedback, (string) json_encode([
                'language' => Locale::getDisplayLanguage($language, 'en') ?: $language,
                'question' => $question,
                'answer' => $answer,
                'candidate_skills' => $candidateSkills,
            ]), $user);
        } catch (AiUnavailable $exception) {
            return ['feedback' => $rules, 'source' => ContentSource::Rules, 'aiError' => $exception->reason];
        }

        $notes = fn (mixed $items): array => array_values(array_map(
            fn (string $text): array => ['text' => $text],
            array_filter(array_map(fn (mixed $item): string => is_string($item) ? trim($item) : '', (array) $items), fn (string $text): bool => $text !== ''),
        ));
        $feedback = ['strengths' => $notes($response->data['strengths'] ?? []), 'improvements' => $notes($response->data['improvements'] ?? [])];

        return $feedback['strengths'] === [] && $feedback['improvements'] === []
            ? ['feedback' => $rules, 'source' => ContentSource::Rules, 'aiError' => null]
            : ['feedback' => $feedback, 'source' => ContentSource::Ai, 'aiError' => null];
    }

    /**
     * @return Feedback
     */
    private function checks(string $answer, ?string $skill): array
    {
        $strengths = [];
        $improvements = [];

        $words = count(preg_split('/\s+/u', trim($answer), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        if ($words < self::MIN_WORDS) {
            $improvements[] = ['key' => 'too_short'];
        } elseif ($words > self::MAX_WORDS) {
            $improvements[] = ['key' => 'too_long'];
        } else {
            $strengths[] = ['key' => 'good_length'];
        }

        // A number, a percentage or a duration is the cheapest sign of a concrete result.
        preg_match('/\d/u', $answer) === 1 ? $strengths[] = ['key' => 'has_numbers'] : $improvements[] = ['key' => 'add_result'];

        if ($skill !== null && $skill !== '') {
            mb_stripos($answer, $skill) !== false ? $strengths[] = ['key' => 'names_skill'] : $improvements[] = ['key' => 'name_skill'];
        }

        return ['strengths' => $strengths, 'improvements' => $improvements];
    }
}
