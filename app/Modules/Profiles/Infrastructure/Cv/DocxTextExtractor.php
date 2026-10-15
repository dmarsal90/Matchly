<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Infrastructure\Cv;

use App\Modules\Profiles\Domain\Contracts\CvTextExtractor;
use App\Modules\Profiles\Infrastructure\Models\CvDocument;
use App\Modules\Shared\Infrastructure\Files\TemporaryFile;
use RuntimeException;
use ZipArchive;

/**
 * Reads word/document.xml straight from the zip instead of loading the whole
 * document model: faster, and the archive is vetted first (decompressed size,
 * entry count) so a zip bomb never gets inflated.
 */
final readonly class DocxTextExtractor implements CvTextExtractor
{
    private const int MAX_ENTRIES = 2000;

    public function __construct(private int $maxUncompressedBytes) {}

    public function supports(string $mimeType): bool
    {
        return $mimeType === CvDocument::DOCX;
    }

    public function extract(string $contents): string
    {
        if (! str_starts_with($contents, "PK\x03\x04")) {
            throw new RuntimeException('Not a DOCX document.');
        }

        $file = TemporaryFile::create('cv', 'docx');

        try {
            file_put_contents($file, $contents);

            return $this->read($file);
        } finally {
            @unlink($file);
        }
    }

    private function read(string $file): string
    {
        $zip = new ZipArchive;
        if ($zip->open($file, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Corrupt DOCX archive.');
        }

        try {
            if ($zip->numFiles > self::MAX_ENTRIES) {
                throw new RuntimeException('DOCX archive has too many entries.');
            }

            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $total += (int) ($zip->statIndex($i)['size'] ?? 0);
                if ($total > $this->maxUncompressedBytes) {
                    throw new RuntimeException('DOCX archive expands beyond the allowed size.');
                }
            }

            $xml = $zip->getFromName('word/document.xml');
            if ($xml === false) {
                throw new RuntimeException('DOCX archive has no document body.');
            }
        } finally {
            $zip->close();
        }

        return TextNormalizer::normalize($this->textFromXml($xml));
    }

    private function textFromXml(string $xml): string
    {
        // Paragraph and line breaks become new lines, tabs become spaces; the rest is markup.
        $xml = (string) preg_replace(['#</w:p>#', '#<w:br\s*/>#', '#<w:tab\s*/>#'], ["\n", "\n", ' '], $xml);

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
