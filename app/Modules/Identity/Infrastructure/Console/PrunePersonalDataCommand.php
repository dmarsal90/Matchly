<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Console;

use App\Modules\Applications\Infrastructure\Models\SharedReport;
use App\Modules\Identity\Application\Actions\DeleteAccount;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Domain\Enums\ImportStatus;
use App\Modules\Profiles\Infrastructure\Models\CvDocument;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

#[Signature('privacy:prune')]
#[Description('Apply data-retention rules (config/privacy.php)')]
final class PrunePersonalDataCommand extends Command
{
    public function handle(DeleteAccount $deleteAccount): int
    {
        $unverifiedDays = (int) config('privacy.retention.unverified_account_days');
        $deleted = 0;

        User::query()
            ->whereNull('email_verified_at')
            ->where('created_at', '<', CarbonImmutable::now()->subDays($unverifiedDays))
            ->each(function (User $user) use ($deleteAccount, &$deleted): void {
                $deleteAccount->handle($user);
                $deleted++;
            });

        $cleared = 0;
        $months = config('privacy.retention.inactive_cv_text_months');
        if (is_int($months)) {
            $cleared = CvDocument::query()
                ->whereNotNull('extracted_text')
                ->whereHas('user', fn ($query) => $query->where(fn ($inactive) => $inactive
                    ->where('last_active_at', '<', CarbonImmutable::now()->subMonths($months))
                    ->orWhere(fn ($never) => $never->whereNull('last_active_at')->where('created_at', '<', CarbonImmutable::now()->subMonths($months)))))
                ->update(['extracted_text' => null, 'parsed_data' => null]);
        }

        $discarded = ProfileImport::query()
            ->whereIn('status', [ImportStatus::PendingReview, ImportStatus::Failed, ImportStatus::Processing])
            ->where('updated_at', '<', CarbonImmutable::now()->subDays((int) config('privacy.retention.unreviewed_import_days')))
            ->delete();

        // A shared report that expired is gone for good, not just hidden.
        SharedReport::query()->where('expires_at', '<', CarbonImmutable::now())->delete();

        // Read notifications are kept for a while for reference, then removed.
        $notifications = DatabaseNotification::query()
            ->whereNotNull('read_at')
            ->where('created_at', '<', CarbonImmutable::now()->subDays((int) config('privacy.retention.read_notification_days')))
            ->delete();

        $this->components->info("Deleted {$notifications} old notifications; deleted {$deleted} unverified accounts; cleared extracted text of {$cleared} CVs from inactive accounts; discarded {$discarded} unreviewed imports.");

        return self::SUCCESS;
    }
}
