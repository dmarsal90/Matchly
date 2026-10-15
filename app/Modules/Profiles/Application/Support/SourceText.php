<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Support;

/**
 * Guard against invented data: an item the AI returns is kept only when its
 * key text actually appears in the source it was taken from.
 */
final readonly class SourceText
{
    private string $normalized;

    public function __construct(string $text)
    {
        $this->normalized = self::normalize($text);
    }

    public function contains(?string $needle): bool
    {
        if ($needle === null) {
            return false;
        }

        $needle = self::normalize($needle);

        return $needle !== '' && str_contains($this->normalized, $needle);
    }

    /**
     * Share of the meaningful words (4+ letters) of a text that occur in
     * the source: a faithful summary scores high, an embellished one low.
     */
    public function overlap(string $text): float
    {
        $words = array_unique(array_filter(explode(' ', self::normalize($text)), fn (string $word): bool => mb_strlen($word) >= 4));
        if ($words === []) {
            return 0.0;
        }

        $sourceWords = array_flip(explode(' ', $this->normalized));
        $found = count(array_filter($words, fn (string $word): bool => isset($sourceWords[$word])));

        return $found / count($words);
    }

    /** A date is credible when its year is in the source. */
    public function containsYearOf(?string $date): bool
    {
        return $date !== null && preg_match('/^(\d{4})/', $date, $match) === 1 && str_contains($this->normalized, $match[1]);
    }

    private static function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = (string) preg_replace('/[^\p{L}\p{N}+#.\s]+/u', ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
