<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Settings;

use App\Modules\Identity\Application\Actions\IssueExtensionToken;
use App\Modules\Identity\Infrastructure\Models\ExtensionToken;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Tokens that let the browser extension read approved applications. */
final class ExtensionController
{
    public function edit(Request $request, #[CurrentUser] User $user): Response
    {
        return Inertia::render('settings/Extension', [
            'tokens' => $user->extensionTokens()->orderBy('id')->get()->map(fn (ExtensionToken $token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at->toIso8601String(),
            ])->all(),
            'baseUrl' => rtrim((string) config('app.url'), '/'),
            // Shown once, right after creating it.
            'newToken' => $request->session()->pull('extension_token'),
            'max' => IssueExtensionToken::MAX_PER_USER,
        ]);
    }

    public function store(Request $request, #[CurrentUser] User $user, IssueExtensionToken $issue): RedirectResponse
    {
        $name = $request->validate(['name' => ['required', 'string', 'max:60']])['name'];

        $request->session()->flash('extension_token', $issue->handle($user, trim($name)));

        return back();
    }

    public function destroy(ExtensionToken $token, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless($token->user_id === $user->id, 403);

        $token->delete();

        return back();
    }
}
