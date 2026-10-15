<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Actions;

use App\Modules\Applications\Application\Generators\CoverLetterWriter;
use App\Modules\Applications\Domain\Data\ResumeData;
use App\Modules\Applications\Domain\Enums\ContentSource;
use App\Modules\Applications\Domain\Enums\PackageStatus;
use App\Modules\Applications\Infrastructure\Models\ApplicationPackage;
use Illuminate\Validation\ValidationException;

/**
 * Saves the person's edits. Any change sends an approved package back to
 * draft: what gets exported is always what was last approved. Letter
 * warnings are recomputed on the edited text.
 */
final readonly class UpdatePackage
{
    public function __construct(private CoverLetterWriter $letters) {}

    /**
     * @param  array<string, mixed>|null  $resume
     */
    public function handle(ApplicationPackage $package, ?string $letter, ?array $resume): void
    {
        if (! in_array($package->status, [PackageStatus::Draft, PackageStatus::Approved], true)) {
            throw ValidationException::withMessages(['package' => __('quick_apply.not_editable')]);
        }

        $changes = [];
        $edited = $resume !== null ? ResumeData::fromArray($resume) : null;
        $current = $package->resumeData();

        if ($letter !== null && trim($letter) !== trim((string) $package->cover_letter)) {
            $changes['cover_letter'] = trim($letter);
            $changes['letter_source'] = ContentSource::User;
        }
        if ($edited !== null && $edited->toArray() !== $current?->toArray()) {
            $changes['resume'] = $edited->toArray();
            $changes['resume_source'] = ContentSource::User;
        }

        if ($changes === []) {
            return;
        }

        $master = $package->masterResumeData();
        $text = (string) ($changes['cover_letter'] ?? $package->cover_letter);
        $kept = array_values(array_filter((array) $package->warnings, fn (array $warning): bool => ! in_array($warning['key'], ['unbacked_skills', 'years_claim'], true)));

        $package->fill([
            ...$changes,
            'warnings' => [...$kept, ...($master !== null ? $this->letters->warnings($text, $master, $package->user?->profile?->years_experience) : [])],
            'status' => PackageStatus::Draft,
            'approved_at' => null,
        ])->save();
    }
}
