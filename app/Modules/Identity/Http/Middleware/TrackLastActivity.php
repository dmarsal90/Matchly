<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Infrastructure\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records activity at most once a day; drives data-retention rules.
 */
final class TrackLastActivity
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ($user->last_active_at === null || $user->last_active_at->lt(CarbonImmutable::now()->subDay()))) {
            User::query()->whereKey($user->id)->update(['last_active_at' => CarbonImmutable::now()]);
        }

        return $next($request);
    }
}
