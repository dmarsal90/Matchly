<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Controllers;

use App\Modules\Applications\Application\Actions\DiscoverCompanyFacts;
use App\Modules\Applications\Domain\Enums\NoteOrigin;
use App\Modules\Applications\Domain\Services\CompanyKey;
use App\Modules\Applications\Http\Requests\DiscoverCompanyFactsRequest;
use App\Modules\Applications\Http\Requests\StoreCompanyNoteRequest;
use App\Modules\Applications\Infrastructure\Models\CompanyNote;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** What the person knows about a company; used (once approved) in the letter and the interview pack. */
final class CompanyNoteController
{
    private const int MAX_NOTES_PER_COMPANY = 20;

    public function store(StoreCompanyNoteRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        $key = CompanyKey::from($request->company());
        abort_if($key === '', 422);

        $existing = CompanyNote::query()->where('user_id', $user->id)->where('company_key', $key)->count();
        abort_if($existing >= self::MAX_NOTES_PER_COMPANY, 422);

        $note = new CompanyNote([
            'company_key' => $key,
            'company' => $request->company(),
            'fact' => $request->fact(),
            'source_url' => $request->sourceUrl(),
            'origin' => NoteOrigin::Manual,
            // The person wrote it, so it is approved from the start.
            'approved' => true,
        ]);
        $note->user()->associate($user);
        $note->save();

        return back();
    }

    /** Reads the company's public pages and proposes facts; nothing is used until approved. */
    public function discover(DiscoverCompanyFactsRequest $request, #[CurrentUser] User $user, DiscoverCompanyFacts $discover): RedirectResponse
    {
        $result = $discover->handle($user, $request->company(), $request->siteUrl());

        Inertia::flash('toast', $result['added'] > 0
            ? ['type' => 'success', 'message' => trans_choice('company_notes.discover.found', $result['added'], ['count' => $result['added']])]
            : ['type' => 'info', 'message' => __($result['pages'] === 0 ? 'company_notes.discover.unreachable' : 'company_notes.discover.nothing')]);

        return back();
    }

    /** The person has read the suggestion and accepts it: from now on the letter may use it. */
    public function approve(CompanyNote $note): RedirectResponse
    {
        Gate::authorize('update', $note);

        $note->forceFill(['approved' => true])->save();

        return back();
    }

    public function destroy(CompanyNote $note): RedirectResponse
    {
        Gate::authorize('delete', $note);

        $note->delete();

        return back();
    }
}
