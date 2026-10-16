<?php

declare(strict_types=1);

namespace App\Modules\Applications\Infrastructure\Console;

use App\Modules\Applications\Application\Actions\SendDeadlineReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('applications:deadlines {--days= : Days of notice (default: config applications.deadline_warning_days)}')]
#[Description('Warn people about application deadlines that are coming up')]
final class SendDeadlineRemindersCommand extends Command
{
    public function handle(SendDeadlineReminders $reminders): int
    {
        $days = $this->option('days');
        $this->components->info("Sent {$reminders->handle($days === null ? null : (int) $days)} deadline reminders.");

        return self::SUCCESS;
    }
}
