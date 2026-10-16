<?php

declare(strict_types=1);

namespace App\Modules\Alerts\Http\Controllers;

use App\Modules\Alerts\Infrastructure\Models\FollowedCompany;
use App\Modules\Applications\Domain\Services\CompanyKey;
use App\Modules\Identity\Infrastructure\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Companies the person follows: they are told when a new job from one of them is ingested. */
final class CompanyRadarController
{
    private const int MAX_FOLLOWS = 50;

    public function index(#[CurrentUser] User $user): Response
    {
        return Inertia::render('alerts/Radar', [
            'companies' => FollowedCompany::query()->where('user_id', $user->id)->orderBy('company')->get()->map(fn (FollowedCompany $follow): array => [
                'id' => $follow->id,
                'company' => $follow->company,
                'followed_at' => $follow->created_at->toIso8601String(),
                'last_notified_at' => $follow->last_notified_at?->toIso8601String(),
            ])->all(),
            'max' => self::MAX_FOLLOWS,
        ]);
    }

    public function store(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        $company = trim($request->validate(['company' => ['required', 'string', 'max:160']])['company']);
        $key = CompanyKey::from($company);

        $existing = FollowedCompany::query()->where('user_id', $user->id)->where('company_key', $key)->exists();
        if (! $existing && FollowedCompany::query()->where('user_id', $user->id)->count() >= self::MAX_FOLLOWS) {
            return back()->withErrors(['company' => __('radar.too_many', ['max' => self::MAX_FOLLOWS])]);
        }

        if (! $existing) {
            (new FollowedCompany)->forceFill(['user_id' => $user->id, 'company_key' => $key, 'company' => $company, 'last_checked_at' => CarbonImmutable::now()])->save();
        }

        return back();
    }

    public function destroy(FollowedCompany $follow, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless($follow->user_id === $user->id, 403);

        $follow->delete();

        return back();
    }
}
