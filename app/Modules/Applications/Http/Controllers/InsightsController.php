<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Controllers;

use App\Modules\Applications\Application\Queries\ApplicationFunnel;
use App\Modules\Applications\Application\Queries\ExperimentAdvisor;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Matching\Application\Queries\SkillGapReport;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** What the person's job search says about them: skills to learn next. */
final class InsightsController
{
    public function index(#[CurrentUser] User $user, SkillGapReport $gaps, ApplicationFunnel $funnel, ExperimentAdvisor $advisor): Response
    {
        $figures = $funnel->for($user->id);

        return Inertia::render('insights/Index', [
            'skillGaps' => $gaps->for($user->id),
            'funnel' => $figures,
            'experiments' => $advisor->advise($figures),
        ]);
    }

    /** The same figures as one self-contained HTML file to keep, print or share. */
    public function report(#[CurrentUser] User $user, SkillGapReport $gaps, ApplicationFunnel $funnel, ExperimentAdvisor $advisor): HttpResponse
    {
        $now = CarbonImmutable::now();
        $figures = $funnel->for($user->id);
        $html = view('reports.insights', [
            'name' => $user->name,
            'generatedAt' => $now->settings(['locale' => app()->getLocale()])->isoFormat('LL'),
            'gaps' => $gaps->for($user->id),
            'funnel' => $figures,
            'experiments' => $advisor->advise($figures),
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.Str::slug((string) config('app.name')).'-report-'.$now->toDateString().'.html"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
