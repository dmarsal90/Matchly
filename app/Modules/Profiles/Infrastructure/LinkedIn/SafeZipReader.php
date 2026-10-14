<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Infrastructure\LinkedIn;

use App\Modules\Profiles\Domain\Exceptions\ImportFailed;
use ZipArchive;

/**
 * Reads whitelisted files out of an untrusted ZIP without extracting it.
 *
 * - Nothing is written to disk: entries are streamed into memory, so there
 *   is no path to traverse ("zip slip") and no leftover to clean up.
 * - Entry names with traversal, absolute paths, NUL bytes or symlink
 *   attributes make the whole archive suspicious and it is rejected.
 * - Zip bombs: entry count, total declared size, per-file compression ratio
 *   and the real number of bytes inflated are all capped (declared sizes
 *   can lie, so reading stops at the limit regardless).
 * - Everything outside the whitelist (messages, connections…) is never read.
 */
final readonly class SafeZipReader
{
    private const int CHUNK = 65536;

    private const int RATIO_MIN_BYTES = 1024 * 1024;

    public function __construct(
        private int $maxEntries,
        private int $maxUncompressedBytes,
        private int $maxFileBytes,
        private int $maxCompressionRatio,
    ) {}

    /**
     * @param  array<string, string>  $whitelist  lower-case basename => key
     * @return array{files: array<string, string>, names: array<string, string>, skipped: int} key => contents, key => entry name
     */
    public function read(string $path, array $whitelist): array
    {
        $head = (string) @file_get_contents($path, length: 4);
        if ($head !== "PK\x03\x04") {
            throw ImportFailed::because(ImportFailed::INVALID_ARCHIVE, 'Not a ZIP archive.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw ImportFailed::because(ImportFailed::INVALID_ARCHIVE, 'Corrupt ZIP archive.');
        }

        try {
            return $this->readEntries($zip, $whitelist);
        } finally {
            $zip->close();
        }
    }

    /**
     * @param  array<string, string>  $whitelist
     * @return array{files: array<string, string>, names: array<string, string>, skipped: int}
     */
    private function readEntries(ZipArchive $zip, array $whitelist): array
    {
        if ($zip->numFiles > $this->maxEntries) {
            throw ImportFailed::because(ImportFailed::UNSAFE_ARCHIVE, "Archive has {$zip->numFiles} entries.");
        }

        $total = 0;
        /** @var array<string, int> $selected key => index */
        $selected = [];
        $skipped = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index, ZipArchive::FL_UNCHANGED);
            if ($stat === false) {
                throw ImportFailed::because(ImportFailed::INVALID_ARCHIVE, "Unreadable entry {$index}.");
            }

            $name = (string) $stat['name'];
            if (! $this->safeName($name) || $this->isSymlink($zip, $index)) {
                throw ImportFailed::because(ImportFailed::UNSAFE_ARCHIVE, 'Unsafe entry name or type.');
            }

            $total += (int) $stat['size'];
            if ($total > $this->maxUncompressedBytes) {
                throw ImportFailed::because(ImportFailed::UNSAFE_ARCHIVE, 'Archive expands beyond the allowed size.');
            }

            $key = str_ends_with($name, '/') ? null : ($whitelist[strtolower(basename($name))] ?? null);
            if ($key === null) {
                $skipped++;

                continue;
            }

            // Duplicates (same file in two folders): the shallowest wins.
            if (! isset($selected[$key]) || substr_count($name, '/') < substr_count((string) $zip->getNameIndex($selected[$key]), '/')) {
                $selected[$key] = $index;
            }
        }

        $files = [];
        $names = [];
        foreach ($selected as $key => $index) {
            $files[$key] = $this->readEntry($zip, $index);
            $names[$key] = basename((string) $zip->getNameIndex($index));
        }

        return ['files' => $files, 'names' => $names, 'skipped' => $skipped];
    }

    private function readEntry(ZipArchive $zip, int $index): string
    {
        $stat = $zip->statIndex($index);
        if ($stat === false || $stat['encryption_method'] !== 0) {
            throw ImportFailed::because(ImportFailed::INVALID_ARCHIVE, 'Encrypted or unreadable entry.');
        }

        $size = (int) $stat['size'];
        $compressed = max(1, (int) $stat['comp_size']);
        if ($size > $this->maxFileBytes || ($size > self::RATIO_MIN_BYTES && $size / $compressed > $this->maxCompressionRatio)) {
            throw ImportFailed::because(ImportFailed::UNSAFE_ARCHIVE, 'Entry too large or too compressed.');
        }

        $stream = $zip->getStreamIndex($index);
        if ($stream === false) {
            throw ImportFailed::because(ImportFailed::INVALID_ARCHIVE, 'Cannot read entry.');
        }

        $contents = '';
        try {
            while (! feof($stream)) {
                $chunk = fread($stream, self::CHUNK);
                if ($chunk === false) {
                    throw ImportFailed::because(ImportFailed::INVALID_ARCHIVE, 'Cannot read entry.');
                }
                $contents .= $chunk;
                // The declared size may lie: stop inflating at the real limit.
                if (strlen($contents) > $this->maxFileBytes) {
                    throw ImportFailed::because(ImportFailed::UNSAFE_ARCHIVE, 'Entry inflates beyond its declared size.');
                }
            }
        } finally {
            fclose($stream);
        }

        return $contents;
    }

    private function safeName(string $name): bool
    {
        if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\')) {
            return false;
        }

        if (str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name) === 1) {
            return false;
        }

        return ! in_array('..', explode('/', $name), true);
    }

    private function isSymlink(ZipArchive $zip, int $index): bool
    {
        $system = 0;
        $attributes = 0;
        if (! $zip->getExternalAttributesIndex($index, $system, $attributes)) {
            return false;
        }

        // Unix mode lives in the high 16 bits; S_IFLNK = 0120000.
        return $system === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000;
    }
}
