<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Actions;

use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Domain\Enums\OAuthProvider;
use App\Modules\Identity\Infrastructure\Models\SocialAccount;
use App\Modules\Profiles\Domain\Enums\ImportStatus;
use App\Modules\Profiles\Domain\Exceptions\ImportFailed;
use App\Modules\Profiles\Infrastructure\GitHub\GitHubProfileReader;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Runs a queued GitHub import and leaves it ready for review (or failed with
 * a reason the user can understand).
 */
final readonly class ReadGithubProfile
{
    public function __construct(
        private GitHubProfileReader $reader,
        private LoggerInterface $logger,
    ) {}

    public function handle(ProfileImport $import): void
    {
        if ($import->status !== ImportStatus::Processing) {
            return;
        }

        $user = $import->user;
        // Consent may have been withdrawn while the job waited in the queue.
        if ($user === null || ! $user->hasConsented(ConsentPurpose::GithubImport)) {
            $import->delete();

            return;
        }

        try {
            $payload = $this->reader->read((string) $import->reference, $this->tokenFor($user->id, (string) $import->reference));

            $import->fill(['status' => ImportStatus::PendingReview, 'payload' => $payload, 'reference' => $payload['username'], 'error' => null])->save();
        } catch (ImportFailed $exception) {
            $import->fill(['status' => ImportStatus::Failed, 'error' => $exception->reason])->save();
        } catch (Throwable $exception) {
            $this->logger->error('GitHub import failed', ['import' => $import->id, 'exception' => $exception]);
            $import->fill(['status' => ImportStatus::Failed, 'error' => ImportFailed::UNAVAILABLE])->save();
        }
    }

    /**
     * The user's own OAuth token, when the account is linked, raises the rate
     * limit. It is only sent to public endpoints and only for their own login.
     */
    private function tokenFor(int $userId, string $username): ?string
    {
        $account = SocialAccount::query()
            ->where('user_id', $userId)
            ->where('provider', OAuthProvider::Github)
            ->first();

        return $account !== null && strcasecmp((string) $account->nickname, $username) === 0 ? $account->token : null;
    }
}
