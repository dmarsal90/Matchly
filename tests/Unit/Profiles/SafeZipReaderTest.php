<?php

declare(strict_types=1);

use App\Modules\Profiles\Domain\Exceptions\ImportFailed;
use App\Modules\Profiles\Infrastructure\LinkedIn\SafeZipReader;

const WHITELIST = ['positions.csv' => 'positions', 'skills.csv' => 'skills'];

function zipReader(int $maxEntries = 100, int $maxUncompressed = 10 * 1024 * 1024, int $maxFile = 1024 * 1024, int $ratio = 100): SafeZipReader
{
    return new SafeZipReader($maxEntries, $maxUncompressed, $maxFile, $ratio);
}

function readZip(string $path, ?SafeZipReader $reader = null): array
{
    try {
        return ($reader ?? zipReader())->read($path, WHITELIST);
    } finally {
        @unlink($path);
    }
}

function expectRejected(string $path, string $reason, ?SafeZipReader $reader = null): void
{
    try {
        readZip($path, $reader);
        test()->fail('The archive was not rejected.');
    } catch (ImportFailed $exception) {
        expect($exception->reason)->toBe($reason);
    }
}

it('reads only whitelisted files, case-insensitively and from any folder', function (): void {
    $result = readZip(makeZip([
        'Positions.csv' => "Title\nDev",
        'export/SKILLS.CSV' => "Name\nPHP",
        'messages.csv' => 'secret',
        'Connections.csv' => 'secret',
        'media/' => '',
    ]));

    expect($result['files'])->toBe(['positions' => "Title\nDev", 'skills' => "Name\nPHP"])
        ->and($result['names'])->toBe(['positions' => 'Positions.csv', 'skills' => 'SKILLS.CSV'])
        ->and($result['skipped'])->toBe(3);
});

it('prefers the shallowest copy of a duplicated file', function (): void {
    $result = readZip(makeZip(['deep/nested/Skills.csv' => 'deep', 'Skills.csv' => 'root']));

    expect($result['files']['skills'])->toBe('root');
});

it('rejects files that are not ZIP archives', function (): void {
    $path = sys_get_temp_dir().'/test-'.bin2hex(random_bytes(4)).'.zip';
    file_put_contents($path, '%PDF-1.4 not a zip');

    expectRejected($path, ImportFailed::INVALID_ARCHIVE);
});

it('rejects path traversal and absolute entry names (zip slip)', function (string $name): void {
    expectRejected(makeZip(['Skills.csv' => "Name\nPHP", $name => 'x']), ImportFailed::UNSAFE_ARCHIVE);
})->with(['../../etc/passwd', 'export/../../evil.csv', '/etc/cron.d/evil', 'C:/Windows/evil.csv', 'dir\\..\\evil.csv']);

it('rejects symlink entries', function (): void {
    $path = makeZip(['Skills.csv' => '/etc/passwd'], function (ZipArchive $zip): void {
        $zip->setExternalAttributesName('Skills.csv', ZipArchive::OPSYS_UNIX, (0120777 << 16));
    });

    expectRejected($path, ImportFailed::UNSAFE_ARCHIVE);
});

it('rejects archives with too many entries', function (): void {
    $entries = [];
    for ($i = 0; $i < 11; $i++) {
        $entries["file{$i}.txt"] = 'x';
    }

    expectRejected(makeZip($entries), ImportFailed::UNSAFE_ARCHIVE, zipReader(maxEntries: 10));
});

it('rejects archives that expand beyond the total limit (zip bomb)', function (): void {
    expectRejected(makeZip(['padding.bin' => str_repeat('0', 2 * 1024 * 1024)]), ImportFailed::UNSAFE_ARCHIVE, zipReader(maxUncompressed: 1024 * 1024));
});

it('rejects whitelisted files that are too big or suspiciously compressed', function (): void {
    $bomb = str_repeat('A', 3 * 1024 * 1024);

    expectRejected(makeZip(['Skills.csv' => $bomb]), ImportFailed::UNSAFE_ARCHIVE, zipReader(maxFile: 2 * 1024 * 1024));
    expectRejected(makeZip(['Skills.csv' => $bomb]), ImportFailed::UNSAFE_ARCHIVE, zipReader(maxFile: 10 * 1024 * 1024, ratio: 50));
});

it('returns no files when nothing is whitelisted', function (): void {
    expect(readZip(makeZip(['messages.csv' => 'x']))['files'])->toBe([]);
});
