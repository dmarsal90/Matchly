<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Support;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Domain\Enums\ImportStatus;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * One import in flight per source: starting a new one replaces any earlier
 * unconfirmed one. The confirmed import stays until the new one is confirmed.
 */
final class PendingImports
{
    /** A processing import older than this is considered dead. */
    private const int STALE_MINUTES = 15;

    public function start(User $user, SkillSource $source, ?string $reference = null, ImportStatus $status = ImportStatus::Processing): ProfileImport
    {
        $running = $user->profileImports()
            ->where('source', $source)
            ->where('status', ImportStatus::Processing)
            ->where('created_at', '>', CarbonImmutable::now()->subMinutes(self::STALE_MINUTES))
            ->exists();

        if ($running) {
            throw ValidationException::withMessages(['import' => __('skills.import.in_progress')]);
        }

        $user->profileImports()
            ->where('source', $source)
            ->whereIn('status', [ImportStatus::Processing, ImportStatus::PendingReview, ImportStatus::Failed])
            ->delete();

        return $user->profileImports()->create([
            'source' => $source,
            'status' => $status,
            'reference' => $reference,
        ]);
    }
}
