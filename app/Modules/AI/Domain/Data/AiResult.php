<?php

declare(strict_types=1);

namespace App\Modules\AI\Domain\Data;

/** What a provider answered, with its token usage. */
final readonly class AiResult
{
    /**
     * @param  array<string, mixed>|null  $data  decoded JSON answer (null when the answer was not JSON)
     */
    public function __construct(
        public ?array $data,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cacheReadTokens = 0,
        public int $cacheWriteTokens = 0,
    ) {}

    /**
     * Decodes a JSON object from model text, tolerating code fences and prose around it.
     *
     * @return array<string, mixed>|null
     */
    public static function decode(string $text): ?array
    {
        $text = trim($text);
        $text = (string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text);

        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            $start = strpos($text, '{');
            $end = strrpos($text, '}');
            $decoded = $start !== false && $end !== false ? json_decode(substr($text, $start, $end - $start + 1), true) : null;
        }

        return is_array($decoded) && ! array_is_list($decoded) ? $decoded : null;
    }
}
