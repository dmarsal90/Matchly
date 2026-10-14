<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Localization;

use Locale;

/**
 * Languages offered in profiles (ISO 639 codes) and the reverse lookup of a
 * language name written in any common locale ("Inglés", "English",
 * "Anglais" → "en"), for imports that only carry names.
 */
final class Languages
{
    /** Mirrors the list offered by the profile form. */
    public const array CODES = [
        'en', 'es', 'pt', 'fr', 'de', 'it', 'nl', 'pl', 'ro', 'uk', 'ru', 'tr', 'ar', 'he', 'hi', 'bn', 'ur', 'zh', 'ja', 'ko',
        'vi', 'th', 'id', 'ms', 'fil', 'sv', 'no', 'da', 'fi', 'cs', 'sk', 'hu', 'el', 'bg', 'hr', 'sr', 'ca', 'eu', 'gl', 'sw',
    ];

    private const array NAME_LOCALES = ['en', 'es', 'pt', 'fr', 'de', 'it', 'nl'];

    /** @var array<string, string>|null */
    private static ?array $byName = null;

    public static function codeFor(string $name): ?string
    {
        $key = self::key($name);
        if ($key === '') {
            return null;
        }

        if (in_array($key, self::CODES, true)) {
            return $key;
        }

        // "English (US)", "Chinese (Mandarin)": the base language is enough.
        $base = self::key((string) preg_replace('/\s*[(\[].*$/', '', $name));

        return self::index()[$key] ?? self::index()[$base] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private static function index(): array
    {
        if (self::$byName !== null) {
            return self::$byName;
        }

        self::$byName = [];
        foreach (self::CODES as $code) {
            foreach (self::NAME_LOCALES as $locale) {
                $display = self::key((string) Locale::getDisplayLanguage($code, $locale));
                if ($display !== '' && $display !== $code) {
                    self::$byName[$display] ??= $code;
                }
            }
        }

        // Common names intl spells differently.
        self::$byName += ['mandarin' => 'zh', 'mandarín' => 'zh', 'cantonese' => 'zh', 'filipino' => 'fil', 'tagalog' => 'fil', 'norwegian' => 'no'];

        return self::$byName;
    }

    private static function key(string $name): string
    {
        return mb_strtolower(trim($name));
    }
}
