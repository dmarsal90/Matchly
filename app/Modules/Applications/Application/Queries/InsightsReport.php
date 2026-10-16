<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Queries;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Matching\Application\Queries\SkillGapReport;
use Carbon\CarbonImmutable;

/** The insights as one self-contained HTML document, for download or for a shared link. */
final readonly class InsightsReport
{
    public function __construct(private SkillGapReport $gaps, private ApplicationFunnel $funnel, private ExperimentAdvisor $advisor) {}

    /**
     * @param  bool  $withName  whether the owner's name appears in the report
     */
    public function html(User $user, bool $withName = true): string
    {
        $figures = $this->funnel->for($user->id);

        return view('reports.insights', [
            'name' => $withName ? $user->name : null,
            'generatedAt' => CarbonImmutable::now()->settings(['locale' => app()->getLocale()])->isoFormat('LL'),
            'gaps' => $this->gaps->for($user->id),
            'funnel' => $figures,
            'experiments' => $this->advisor->advise($figures),
        ])->render();
    }
}
