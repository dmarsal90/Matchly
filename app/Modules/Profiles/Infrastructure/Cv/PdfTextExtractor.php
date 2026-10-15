<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Infrastructure\Cv;

use App\Modules\Profiles\Domain\Contracts\CvTextExtractor;
use App\Modules\Profiles\Infrastructure\Models\CvDocument;
use RuntimeException;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Throwable;

final class PdfTextExtractor implements CvTextExtractor
{
    public function supports(string $mimeType): bool
    {
        return $mimeType === CvDocument::PDF;
    }

    public function extract(string $contents): string
    {
        if (! str_starts_with($contents, '%PDF-')) {
            throw new RuntimeException('Not a PDF document.');
        }

        $config = new Config;
        $config->setRetainImageContent(false);
        // Bounds memory on hostile or huge embedded streams.
        $config->setDecodeMemoryLimit(64 * 1024 * 1024);

        try {
            $text = (new Parser([], $config))->parseContent($contents)->getText();
        } catch (Throwable $exception) {
            throw new RuntimeException('Unreadable PDF: '.$exception->getMessage(), previous: $exception);
        }

        return TextNormalizer::normalize($text);
    }
}
