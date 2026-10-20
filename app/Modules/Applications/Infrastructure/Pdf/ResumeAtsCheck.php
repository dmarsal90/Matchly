<?php

declare(strict_types=1);

namespace App\Modules\Applications\Infrastructure\Pdf;

use App\Modules\Applications\Domain\Data\ResumeData;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Reads the CV PDF back the way an applicant tracking system does (plain
 * text out of the file) and reports what would be lost: nothing readable,
 * contact details that do not come through, or sections it would not find.
 */
final readonly class ResumeAtsCheck
{
    public function __construct(private ResumePdfRenderer $renderer) {}

    /**
     * @return list<array{key: string, params: array<string, string>}>
     */
    public function check(ResumeData $resume, string $language): array
    {
        if ($resume->isEmpty()) {
            return [];
        }

        try {
            $config = new Config;
            $config->setRetainImageContent(false);
            $text = (new Parser([], $config))->parseContent($this->renderer->render($resume, $language))->getText();
        } catch (Throwable) {
            return [['key' => 'ats_unreadable', 'params' => []]];
        }

        $text = $this->squash($text);
        if ($text === '') {
            return [['key' => 'ats_unreadable', 'params' => []]];
        }

        $missing = [];
        $contact = array_filter(['name' => $resume->contact['name'], 'email' => $resume->contact['email'], 'phone' => $resume->contact['phone']]);
        foreach ($contact as $field => $value) {
            if (! str_contains($text, $this->squash((string) $value))) {
                $missing[] = $field;
            }
        }

        $locale = in_array($language, ['es', 'en', 'pt', 'fr', 'it', 'de'], true) ? $language : 'en';
        $sections = array_filter([
            'experience' => $resume->experience !== [],
            'skills' => $resume->skills !== [],
            'education' => $resume->education !== [],
        ]);
        foreach (array_keys($sections) as $section) {
            if (! str_contains(mb_strtolower($text), mb_strtolower((string) __("quick_apply.resume.{$section}", [], $locale)))) {
                $missing[] = $section;
            }
        }

        return $missing === [] ? [] : [['key' => 'ats_missing', 'params' => ['items' => implode(', ', $missing)]]];
    }

    /** Whitespace and line breaks differ between layout and extraction; compare without them. */
    private function squash(string $text): string
    {
        return (string) preg_replace('/\s+/u', '', $text);
    }
}
