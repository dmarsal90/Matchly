<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Actions;

use App\Modules\Identity\Application\Actions\GrantConsent;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Application\Support\PendingImports;
use App\Modules\Profiles\Domain\Enums\ImportStatus;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use App\Modules\Profiles\Domain\Exceptions\ImportFailed;
use App\Modules\Profiles\Infrastructure\LinkedIn\LinkedInExportMapper;
use App\Modules\Profiles\Infrastructure\LinkedIn\SafeZipReader;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;

/**
 * Reads an uploaded LinkedIn export and leaves the result for review. The
 * ZIP is read in place and deleted as soon as it has been read, whatever
 * the outcome: neither it nor the files outside the whitelist are stored.
 */
final readonly class ImportLinkedInExport
{
    /**
     * @param  array<string, string>  $whitelist  lower-case CSV name => section
     */
    public function __construct(
        private SafeZipReader $zipReader,
        private LinkedInExportMapper $mapper,
        private GrantConsent $grantConsent,
        private PendingImports $imports,
        private array $whitelist,
    ) {}

    public function handle(User $user, string $zipPath): ProfileImport
    {
        // The upload form asks for explicit consent; record it before reading anything.
        $this->grantConsent->handle($user, ConsentPurpose::LinkedinImport);

        try {
            $archive = $this->zipReader->read($zipPath, $this->whitelist);
        } finally {
            @unlink($zipPath);
        }

        if ($archive['files'] === []) {
            throw ImportFailed::because(ImportFailed::NOT_A_LINKEDIN_EXPORT, 'No whitelisted CSV in the archive.');
        }

        $data = $this->mapper->map($archive['files']);
        if ($data['skills'] === [] && $data['positions'] === [] && $data['profile'] === null && $data['education'] === []) {
            throw ImportFailed::because(ImportFailed::NOTHING_FOUND, 'The export has no profile data.');
        }

        $import = $this->imports->start($user, SkillSource::Linkedin, status: ImportStatus::PendingReview);
        $import->fill(['payload' => [
            ...$data,
            'files' => array_values($archive['names']),
            'skipped_files' => $archive['skipped'],
        ]])->save();

        return $import;
    }
}
