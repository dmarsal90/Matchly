<?php

declare(strict_types=1);

namespace App\Modules\Matching\Infrastructure\Console;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Matching\Application\Queries\ScorableUsers;
use App\Modules\Matching\Infrastructure\Queue\RecalculateMatchesJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('matching:recalculate {--user= : Only this user id}')]
#[Description('Queue a full rescore of visible jobs for every scorable user (or one)')]
final class RecalculateMatchesCommand extends Command
{
    public function handle(): int
    {
        $query = ScorableUsers::query()->when($this->option('user') !== null, fn ($users) => $users->whereKey((int) $this->option('user')));

        $queued = 0;
        $query->select('id')->each(function (User $user) use (&$queued): void {
            RecalculateMatchesJob::dispatch($user->id);
            $queued++;
        });

        $this->components->info("Queued a rescore for {$queued} users.");

        return self::SUCCESS;
    }
}
