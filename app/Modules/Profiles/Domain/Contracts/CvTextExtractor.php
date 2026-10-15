<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Domain\Contracts;

use RuntimeException;

interface CvTextExtractor
{
    public function supports(string $mimeType): bool;

    /**
     * Plain text of the document, normalized whitespace.
     *
     * @throws RuntimeException when the document cannot be read safely
     */
    public function extract(string $contents): string;
}
