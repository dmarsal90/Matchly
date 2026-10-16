<?php

declare(strict_types=1);

namespace App\Modules\Applications\Infrastructure\Console;

use App\Modules\Applications\Application\Actions\SendFollowUpReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('applications:follow-up {--days= : Days without an answer (default: config applications.follow_up_days)}')]
#[Description('Remind people about applications that have had no answer for a while')]
final class SendFollowUpRemindersCommand extends Command
{
    public function handle(SendFollowUpReminders $reminders): int
    {
        $days = $this->option('days');
        $this->components->info("Sent {$reminders->handle($days === null ? null : (int) $days)} follow-up reminders.");

        return self::SUCCESS;
    }
}
