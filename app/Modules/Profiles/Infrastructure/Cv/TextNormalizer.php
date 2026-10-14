<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Infrastructure\Cv;

final class TextNormalizer
{
    private const int MAX_LENGTH = 100_000;

    public static function normalize(string $text): string
    {
        $text = (string) preg_replace('/[^\P{C}\n\t]/u', '', $text);
        $text = (string) preg_replace('/[ \t]+/u', ' ', $text);
        $text = (string) preg_replace("/\n\s*\n+/u", "\n\n", $text);

        return mb_substr(trim($text), 0, self::MAX_LENGTH);
    }
}
