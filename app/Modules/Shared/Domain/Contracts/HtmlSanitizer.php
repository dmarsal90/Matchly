<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Contracts;

interface HtmlSanitizer
{
    /**
     * Returns HTML safe to render with v-html: an allow-list of formatting
     * tags, no scripts, styles or event handlers, external links hardened.
     */
    public function sanitize(string $html): string;
}
