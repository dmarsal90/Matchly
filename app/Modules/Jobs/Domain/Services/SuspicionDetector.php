<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Services;

use App\Modules\Jobs\Domain\Data\JobData;

/**
 * Cheap heuristics for scam or spam postings. A flagged job is stored but
 * hidden until an admin approves or closes it from the admin panel.
 */
final class SuspicionDetector
{
    /** @var array<string, string> reason => pattern over title + description text */
    private const array PATTERNS = [
        // Only contact instructions count: messaging platforms are also products and marketing channels.
        'off_platform_contact' => '/\b(?:contact|message|reach|text|dm|write to|apply|send (?:your )?(?:cv|resume))\b[^.]{0,40}?\b(?:on|via|through|over|at)\s+(?:telegram|whatsapp|signal|wechat|kik)\b|\b(?:telegram|whatsapp)\s*(?::|@|\+)\s*[@+]?[\w\d]/i',
        'upfront_payment' => '/\b(registration|training|starter kit|application|processing)\s+fee\b|\bpay (?:for|a) (?:your )?(?:training|equipment|kit)\b/i',
        'unrealistic_pay' => '/\b(earn|make)\s+(?:up to\s+)?[$€£]\s?\d{3,}[\d,]*\s*(?:\/|per|a)\s*(?:day|week)\b/i',
        'money_mule' => '/\b(receive|process|transfer) (?:payments|funds|money) (?:on behalf|for (?:our|the) company)\b|\breshipping\b|\bpackage forwarding\b/i',
        'personal_documents' => '/\b(send|provide) (?:a copy of )?(?:your )?(?:passport|ssn|social security|bank (?:account )?details)\b/i',
    ];

    /**
     * @return list<string> reasons; empty when nothing looks wrong
     */
    public function reasons(JobData $job): array
    {
        $text = $job->title.' '.html_entity_decode(strip_tags($job->description), ENT_QUOTES | ENT_HTML5);
        // Short descriptions are not a signal: Adzuna and Jooble only send snippets.
        $reasons = [];

        foreach (self::PATTERNS as $reason => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $reasons[] = $reason;
            }
        }

        return $reasons;
    }
}
