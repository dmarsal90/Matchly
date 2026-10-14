<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Actions;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Application\Support\PendingImports;
use App\Modules\Profiles\Domain\Enums\CvStatus;
use App\Modules\Profiles\Domain\Enums\ImportStatus;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use App\Modules\Profiles\Domain\Exceptions\ImportFailed;
use App\Modules\Profiles\Domain\Services\SkillCatalog;
use App\Modules\Profiles\Infrastructure\Models\CvDocument;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;

/**
 * Finds the known skills named in the primary CV and offers them for review.
 * (The AI step adds experience and education structure on top of this.)
 */
final readonly class AnalyzeCvSkills
{
    private const int MAX_SKILLS = 60;

    public function __construct(
        private SkillCatalog $catalog,
        private PendingImports $imports,
    ) {}

    public function handle(User $user): ProfileImport
    {
        /** @var CvDocument|null $cv */
        $cv = $user->cvDocuments()->where('is_primary', true)->where('status', CvStatus::Extracted)->first();
        if ($cv === null || $cv->extracted_text === null || trim($cv->extracted_text) === '') {
            throw ImportFailed::because(ImportFailed::NO_CV_TEXT, 'No extracted primary CV.');
        }

        $mentions = array_slice($this->catalog->detectIn($cv->extracted_text), 0, self::MAX_SKILLS, preserve_keys: true);
        if ($mentions === []) {
            throw ImportFailed::because(ImportFailed::NOTHING_FOUND, 'No known skills in the CV.');
        }

        $skills = [];
        foreach ($mentions as $slug => $count) {
            $skills[] = [
                'skill' => $slug,
                'name' => $this->catalog->label($slug, $slug),
                'level' => null,
                'years' => null,
                'last_used_year' => null,
                'evidence' => ['mentions' => $count],
            ];
        }

        $import = $this->imports->start($user, SkillSource::Cv, (string) $cv->id, ImportStatus::PendingReview);
        $import->fill(['payload' => ['cv' => ['id' => $cv->id, 'name' => $cv->original_name], 'skills' => $skills]])->save();

        return $import;
    }
}
