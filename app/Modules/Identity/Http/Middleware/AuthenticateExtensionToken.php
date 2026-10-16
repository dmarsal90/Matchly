<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Application\Actions\IssueExtensionToken;
use App\Modules\Identity\Infrastructure\Models\ExtensionToken;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Signs in the owner of a browser extension token for one stateless request. */
final class AuthenticateExtensionToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = (string) $request->bearerToken();
        $token = str_starts_with($plain, IssueExtensionToken::PREFIX)
            ? ExtensionToken::query()->with('user')->where('token_hash', IssueExtensionToken::hash($plain))->first()
            : null;

        $user = $token?->user;
        if ($token === null || $user === null) {
            return response()->json(['message' => __('extension.invalid_token')], 401);
        }

        // One write a minute at most is enough to show "last used".
        if ($token->last_used_at === null || $token->last_used_at->lessThan(CarbonImmutable::now()->subMinute())) {
            $token->forceFill(['last_used_at' => CarbonImmutable::now()])->save();
        }

        Auth::setUser($user);

        return $next($request);
    }
}
