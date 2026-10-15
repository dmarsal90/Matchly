<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Actions\ExportPersonalData;
use App\Modules\Identity\Application\Actions\GrantConsent;
use App\Modules\Identity\Application\Actions\RevokeConsent;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Http\Requests\UpdateConsentRequest;
use App\Modules\Identity\Infrastructure\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class PrivacyController
{
    public function edit(#[CurrentUser] User $user): Response
    {
        $active = $user->consents()->whereNull('revoked_at')->get()->keyBy(fn ($consent) => $consent->purpose->value);

        return Inertia::render('settings/Privacy', [
            'consents' => array_map(fn (ConsentPurpose $purpose): array => [
                'purpose' => $purpose->value,
                'granted' => $active->has($purpose->value),
                'granted_at' => $active->get($purpose->value)?->granted_at->toIso8601String(),
            ], ConsentPurpose::cases()),
            'policyVersion' => config('privacy.policy_version'),
        ]);
    }

    public function updateConsent(UpdateConsentRequest $request, #[CurrentUser] User $user, GrantConsent $grant, RevokeConsent $revoke): RedirectResponse
    {
        $request->boolean('granted')
            ? $grant->handle($user, $request->purpose())
            : $revoke->handle($user, $request->purpose());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('privacy.consent_saved')]);

        return back();
    }

    public function export(#[CurrentUser] User $user, ExportPersonalData $export): BinaryFileResponse
    {
        $name = Str::slug((string) config('app.name')).'-data-'.CarbonImmutable::now()->format('Y-m-d').'.zip';

        return response()
            ->download($export->handle($user), $name, ['Content-Type' => 'application/zip', 'Cache-Control' => 'private, no-store'])
            ->deleteFileAfterSend();
    }
}
