<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Controllers;

use App\Modules\AI\Application\AiGateway;
use App\Modules\AI\Application\Queries\AiUsageSummary;
use App\Modules\AI\Http\Requests\UpdateAiKeyRequest;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** AI settings: the user's own Anthropic key and this month's usage against their budget. */
final class AiSettingsController
{
    public function edit(#[CurrentUser] User $user, AiUsageSummary $usage, AiGateway $ai): Response
    {
        $key = $user->anthropic_api_key;

        return Inertia::render('settings/Ai', [
            'hasOwnKey' => filled($key),
            // Only the last characters, so the user can recognise it.
            'keyHint' => filled($key) ? '…'.substr((string) $key, -4) : null,
            'available' => $ai->available($user),
            'consented' => $user->hasConsented(ConsentPurpose::AiProcessing),
            'usage' => $usage->forUser($user->id),
        ]);
    }

    public function update(UpdateAiKeyRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        $user->forceFill(['anthropic_api_key' => (string) $request->validated('anthropic_api_key')])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ai.key_saved')]);

        return back();
    }

    public function destroy(#[CurrentUser] User $user): RedirectResponse
    {
        $user->forceFill(['anthropic_api_key' => null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ai.key_removed')]);

        return back();
    }
}
