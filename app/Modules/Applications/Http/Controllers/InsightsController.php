<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Controllers;

use App\Modules\Applications\Application\Queries\ApplicationFunnel;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Matching\Application\Queries\SkillGapReport;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/** What the person's job search says about them: skills to learn next. */
final class InsightsController
{
    public function index(#[CurrentUser] User $user, SkillGapReport $gaps, ApplicationFunnel $funnel): Response
    {
        return Inertia::render('insights/Index', [
            'skillGaps' => $gaps->for($user->id),
            'funnel' => $funnel->for($user->id),
        ]);
    }
}
