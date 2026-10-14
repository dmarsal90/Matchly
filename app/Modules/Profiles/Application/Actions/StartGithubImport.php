<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Actions;

use App\Modules\Identity\Application\Actions\GrantConsent;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Application\Support\PendingImports;
use App\Modules\Profiles\Domain\Data\GitHubUsername;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;
use App\Modules\Profiles\Infrastructure\Queue\ImportGithubJob;

/** Records consent and queues the read of a public GitHub profile. */
final readonly class StartGithubImport
{
    public function __construct(
        private GrantConsent $grantConsent,
        private PendingImports $imports,
    ) {}

    public function handle(User $user, GitHubUsername $username): ProfileImport
    {
        $this->grantConsent->handle($user, ConsentPurpose::GithubImport);

        $import = $this->imports->start($user, SkillSource::Github, $username->value);

        ImportGithubJob::dispatch($import->id);

        return $import;
    }
}
