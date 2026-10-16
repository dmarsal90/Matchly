<?php

declare(strict_types=1);

namespace App\Modules\Matching\Http\Controllers;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Application\Queries\SalaryBenchmark;
use App\Modules\Matching\Application\Queries\SkillMarket;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/** What the open market asks for and pays, for the skills that matter to the person. */
final class MarketController
{
    public function index(#[CurrentUser] User $user, SkillMarket $market): Response
    {
        return Inertia::render('market/Index', [
            ...$market->for($user->id),
            'minSalarySample' => SalaryBenchmark::MIN_SAMPLE,
        ]);
    }
}
