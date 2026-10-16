<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Actions;

use App\Modules\Identity\Application\Actions\GrantConsent;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Application\Support\PendingImports;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;
use App\Modules\Profiles\Infrastructure\Queue\ImportPortfolioJob;

/** Records consent and queues the read of the person's public portfolio. */
final readonly class StartPortfolioImport
{
    public function __construct(private GrantConsent $grantConsent, private PendingImports $imports) {}

    public function handle(User $user, string $url): ProfileImport
    {
        $this->grantConsent->handle($user, ConsentPurpose::PortfolioImport);

        $import = $this->imports->start($user, SkillSource::Portfolio, $url);

        ImportPortfolioJob::dispatch($import->id);

        return $import;
    }
}
