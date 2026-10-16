<?php

declare(strict_types=1);

namespace App\Modules\Applications\Domain\Services;

use DOMDocument;
use DOMXPath;

/**
 * Picks candidate facts out of a public company page: its own description
 * (meta tags) and the first real sentences of its text. Nothing is rewritten
 * or summarised: every fact is the company's own wording, and the person
 * approves it before it is ever used.
 */
final class CompanyFactExtractor
{
    private const int MIN_LENGTH = 40;

    private const int MAX_LENGTH = 280;

    private const int MAX_PER_PAGE = 4;

    /** Text that is page furniture, not something the company says about itself. */
    private const string BOILERPLATE = '/cookie|privacy|©|copyright|all rights reserved|terms of|sign up|subscribe|newsletter|log ?in|javascript|accept all|política de|aviso legal|todos los derechos|suscr[ií]bete/iu';

    /**
     * @return list<string>
     */
    public function extract(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $candidates = [];

        foreach (['//meta[@name="description"]/@content', '//meta[@property="og:description"]/@content'] as $query) {
            foreach ($xpath->query($query) ?: [] as $node) {
                $candidates[] = (string) $node->nodeValue;
            }
        }

        foreach ($xpath->query('//p[not(ancestor::nav or ancestor::footer or ancestor::header or ancestor::form or ancestor::aside or ancestor::script or ancestor::style or ancestor::noscript)]') ?: [] as $paragraph) {
            $candidates[] = (string) $paragraph->nodeValue;
        }

        $facts = [];
        foreach ($candidates as $text) {
            $fact = $this->clean($text);
            if ($fact === null || in_array(mb_strtolower($fact), array_map(mb_strtolower(...), $facts), true)) {
                continue;
            }
            $facts[] = $fact;
            if (count($facts) >= self::MAX_PER_PAGE) {
                break;
            }
        }

        return $facts;
    }

    private function clean(string $text): ?string
    {
        $text = trim((string) preg_replace(['/[\x00-\x1F\x7F]+/u', '/\s+/u'], ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        if (mb_strlen($text) < self::MIN_LENGTH || preg_match(self::BOILERPLATE, $text) === 1) {
            return null;
        }
        // No contact data or links in a fact.
        if (preg_match('#https?://|www\.|@[\w-]+\.|\+?\d[\d\s().-]{7,}#u', $text) === 1) {
            return null;
        }

        if (mb_strlen($text) > self::MAX_LENGTH) {
            // Cut at the last sentence end that fits; otherwise at a word.
            $head = mb_substr($text, 0, self::MAX_LENGTH);
            $end = max(mb_strrpos($head, '. ') ?: 0, mb_strrpos($head, '! ') ?: 0, mb_strrpos($head, '? ') ?: 0);
            $text = $end >= self::MIN_LENGTH ? mb_substr($head, 0, $end + 1) : rtrim(mb_substr($head, 0, (int) mb_strrpos($head, ' ')), ',;:').'…';
        }

        return mb_strlen($text) >= self::MIN_LENGTH ? $text : null;
    }
}
