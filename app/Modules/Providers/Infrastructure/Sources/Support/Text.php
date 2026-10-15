<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Sources\Support;

final class Text
{
    /** Decodes HTML that some APIs (Greenhouse) send entity-encoded. */
    public static function decodeHtml(string $html): string
    {
        return html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return $value === '' ? null : $value;
    }

    /**
     * Lowercased, trimmed, unique tags without the noise sources add.
     *
     * @param  list<string>  $tags
     * @return list<string>
     */
    public static function tags(array $tags, int $limit = 20): array
    {
        $clean = [];
        foreach ($tags as $tag) {
            $tag = mb_strtolower(trim(str_replace(['-', '_'], ' ', $tag)));
            if ($tag !== '' && mb_strlen($tag) <= 40 && ! in_array($tag, ['remote', 'remote job', 'jobs', 'other'], true)) {
                $clean[$tag] = true;
            }
        }

        return array_slice(array_keys($clean), 0, $limit);
    }
}
