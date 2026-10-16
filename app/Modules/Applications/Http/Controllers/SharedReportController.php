<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Controllers;

use App\Modules\Applications\Application\Actions\ShareInsightsReport;
use App\Modules\Applications\Infrastructure\Models\SharedReport;
use App\Modules\Identity\Infrastructure\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Sharing the insights report by link: create and revoke (signed in), view (anyone with the link). */
final class SharedReportController
{
    public function store(Request $request, #[CurrentUser] User $user, ShareInsightsReport $share): RedirectResponse
    {
        $data = $request->validate([
            'days' => ['required', 'integer', Rule::in(ShareInsightsReport::LIFETIMES)],
            'with_name' => ['boolean'],
        ]);

        $token = $share->handle($user, (int) $data['days'], (bool) ($data['with_name'] ?? false));
        $request->session()->flash('share_url', route('shared-report.show', ['token' => $token]));

        return back();
    }

    public function destroy(SharedReport $report, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless($report->user_id === $user->id, 403);

        $report->delete();

        return back();
    }

    public function show(string $token): Response
    {
        $report = SharedReport::query()->where('token_hash', ShareInsightsReport::hash($token))->where('expires_at', '>', CarbonImmutable::now())->first();
        // Unknown, revoked and expired all look the same.
        abort_if($report === null, 404);

        $report->forceFill(['views' => $report->views + 1, 'last_viewed_at' => CarbonImmutable::now()])->save();

        return response($report->html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
