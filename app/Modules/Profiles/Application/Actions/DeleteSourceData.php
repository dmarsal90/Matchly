<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Actions;

use App\Modules\Profiles\Application\Events\ProfileChanged;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;
use App\Modules\Profiles\Infrastructure\Models\UserSkill;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

/** Forgets everything obtained from one source. The user can import again later. */
final readonly class DeleteSourceData
{
    public function __construct(
        private ConnectionInterface $db,
        private Dispatcher $events,
    ) {}

    public function handle(int $userId, SkillSource $source): void
    {
        $deleted = $this->db->transaction(fn (): int => ProfileImport::query()->where('user_id', $userId)->where('source', $source)->delete()
            + UserSkill::query()->where('user_id', $userId)->where('source', $source)->delete());

        if ($deleted > 0) {
            $this->events->dispatch(new ProfileChanged($userId));
        }
    }
}
