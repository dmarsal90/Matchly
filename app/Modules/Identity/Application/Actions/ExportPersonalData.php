<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Contracts\PersonalDataContributor;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Shared\Infrastructure\Files\TemporaryFile;
use Carbon\CarbonImmutable;
use RuntimeException;
use ZipArchive;

/**
 * Builds a ZIP with everything stored about the user: data.json (machine
 * readable, portable) plus their original files, decrypted.
 */
final readonly class ExportPersonalData
{
    /**
     * @param  iterable<PersonalDataContributor>  $contributors
     */
    public function __construct(private iterable $contributors) {}

    /**
     * @return string path of a temporary ZIP file; the caller deletes it after sending
     */
    public function handle(User $user): string
    {
        $path = TemporaryFile::create('export', 'zip');

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot open the export archive.');
        }

        $document = ['generated_at' => CarbonImmutable::now()->toIso8601String(), 'sections' => []];

        foreach ($this->contributors as $contributor) {
            $part = $contributor->export($user->id);
            $document['sections'][$part->section] = $part->data;

            foreach ($part->files as $name => $contents) {
                $zip->addFromString("files/{$part->section}/".basename($name), $contents);
            }
        }

        $zip->addFromString('data.json', (string) json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $zip->close();

        return $path;
    }
}
