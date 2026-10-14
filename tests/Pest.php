<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Architecture');

/**
 * Decoded provider fixture (real API responses, trimmed) from tests/Fixtures/providers.
 *
 * @return array<mixed>
 */
function providerFixture(string $name): array
{
    return json_decode(providerFixtureRaw($name), true, flags: JSON_THROW_ON_ERROR);
}

function providerFixtureRaw(string $name): string
{
    return (string) file_get_contents(__DIR__."/Fixtures/providers/{$name}");
}

/** A minimal, valid single-page PDF whose text layer contains $text. */
function makePdf(string $text): string
{
    $stream = 'BT /F1 12 Tf 72 720 Td ('.addcslashes($text, '()\\').') Tj ET';
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
        '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
    }

    $xref = strlen($pdf);
    $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
}

/**
 * A minimal DOCX (zip) with the given paragraphs. $padding adds a highly
 * compressible entry to simulate a zip bomb.
 *
 * @param  list<string>  $paragraphs
 */
function makeDocx(array $paragraphs, int $padding = 0): string
{
    $path = sys_get_temp_dir().'/test-'.bin2hex(random_bytes(8)).'.docx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
    $body = implode('', array_map(fn (string $text): string => '<w:p><w:r><w:t>'.htmlspecialchars($text).'</w:t></w:r></w:p>', $paragraphs));
    $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'</w:body></w:document>');
    if ($padding > 0) {
        $zip->addFromString('word/media/padding.bin', str_repeat('0', $padding));
    }
    $zip->close();

    $contents = (string) file_get_contents($path);
    unlink($path);

    return $contents;
}

function linkedinFixture(string $name): string
{
    return (string) file_get_contents(__DIR__."/Fixtures/linkedin/{$name}");
}

/**
 * Writes a ZIP with the given entries to a temp file and returns its path.
 * $tweak can alter the archive (attributes, odd names) before it is closed.
 *
 * @param  array<string, string>  $entries  name => contents
 * @param  (Closure(ZipArchive): void)|null  $tweak
 */
function makeZip(array $entries, ?Closure $tweak = null): string
{
    $path = sys_get_temp_dir().'/test-'.bin2hex(random_bytes(8)).'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    foreach ($entries as $name => $contents) {
        $zip->addFromString($name, $contents);
    }
    if ($tweak !== null) {
        $tweak($zip);
    }
    $zip->close();

    return $path;
}

/**
 * A LinkedIn data export as LinkedIn ships it: the profile CSVs plus files
 * that must never be read (messages, connections).
 */
function linkedinExportZip(): string
{
    $entries = [];
    foreach (['Profile', 'Positions', 'Education', 'Skills', 'Languages', 'Certifications', 'Projects', 'Connections', 'messages'] as $name) {
        $entries["{$name}.csv"] = linkedinFixture("{$name}.csv");
    }

    return makeZip($entries);
}
