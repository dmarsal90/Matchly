<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Infrastructure\LinkedIn;

use League\Csv\Exception as CsvException;
use League\Csv\Reader;

/**
 * Tolerant CSV reading for exported files: any of UTF-8 (with or without
 * BOM), UTF-16 or Windows-1252, optional preamble lines before the header
 * ("Notes: …"), missing or reordered columns and ragged rows.
 */
final class CsvTable
{
    /**
     * @param  list<string>  $expected  column names that identify the header row
     * @return list<array<string, string>> rows keyed by lower-cased column name; missing columns are ''
     */
    public static function rows(string $contents, array $expected, int $maxRows): array
    {
        $contents = self::toUtf8($contents);
        if (trim($contents) === '') {
            return [];
        }

        try {
            $reader = Reader::fromString($contents);
            $reader->setDelimiter(self::delimiter($contents));
            $records = iterator_to_array($reader->getRecords(), false);
        } catch (CsvException) {
            return [];
        }

        $expected = array_map(mb_strtolower(...), $expected);
        $header = null;
        $rows = [];

        foreach ($records as $record) {
            $cells = array_map(fn (mixed $cell): string => trim((string) $cell), $record);

            if ($header === null) {
                $lower = array_map(mb_strtolower(...), $cells);
                if (array_intersect($expected, $lower) !== []) {
                    $header = $lower;
                }

                continue;
            }

            if (implode('', $cells) === '') {
                continue;
            }

            $row = [];
            foreach ($expected as $column) {
                $position = array_search($column, $header, true);
                $row[$column] = $position !== false ? ($cells[$position] ?? '') : '';
            }
            $rows[] = $row;

            if (count($rows) >= $maxRows) {
                break;
            }
        }

        return $rows;
    }

    private static function toUtf8(string $contents): string
    {
        return match (true) {
            str_starts_with($contents, "\xEF\xBB\xBF") => substr($contents, 3),
            str_starts_with($contents, "\xFF\xFE") => (string) mb_convert_encoding(substr($contents, 2), 'UTF-8', 'UTF-16LE'),
            str_starts_with($contents, "\xFE\xFF") => (string) mb_convert_encoding(substr($contents, 2), 'UTF-8', 'UTF-16BE'),
            mb_check_encoding($contents, 'UTF-8') => $contents,
            default => (string) mb_convert_encoding($contents, 'UTF-8', 'Windows-1252'),
        };
    }

    private static function delimiter(string $contents): string
    {
        $firstLine = strtok($contents, "\n") ?: '';

        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }
}
