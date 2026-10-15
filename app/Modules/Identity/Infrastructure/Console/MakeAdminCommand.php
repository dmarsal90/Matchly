<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Console;

use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('users:admin {email : E-mail of an existing account} {--revoke : Remove admin rights} {--verify : Also mark the e-mail as verified (servers without a mail provider)}')]
#[Description('Grant (or revoke) access to the admin panel and Horizon')]
final class MakeAdminCommand extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('email', (string) $this->argument('email'))->first();

        if ($user === null) {
            $this->components->error('No account with that e-mail.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => ! $this->option('revoke')]);
        if ($this->option('verify') && $user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()]);
        }
        $user->save();

        $this->components->info($user->is_admin ? "{$user->email} is now an admin." : "{$user->email} is no longer an admin.");

        return self::SUCCESS;
    }
}
