<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Infrastructure\Models\User;
use Closure;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Password confirmation for sensitive pages, except for OAuth-only accounts
 * that have no password to confirm (they re-authenticated with the provider).
 */
final class RequirePasswordIfSet extends RequirePassword
{
    public function handle($request, Closure $next, $redirectToRoute = null, $passwordTimeoutSeconds = null): Response
    {
        /** @var Request $request */
        $user = $request->user();

        if ($user instanceof User && $user->password === null) {
            return $next($request);
        }

        return parent::handle($request, $next, $redirectToRoute, $passwordTimeoutSeconds);
    }
}
