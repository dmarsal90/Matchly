<?php

declare(strict_types=1);

namespace App\Modules\Alerts\Http\Controllers;

use App\Modules\Alerts\Infrastructure\Models\SavedSearch;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One-click unsubscribe from an alert's e-mails, through a signed link: it
 * works without signing in (from any mail client) and cannot be forged for
 * someone else's alert. The alert itself stays, in-app only.
 */
final class UnsubscribeController
{
    public function show(SavedSearch $savedSearch): Response
    {
        $savedSearch->forceFill(['email' => false])->save();

        return Inertia::render('alerts/Unsubscribed', ['name' => $savedSearch->name]);
    }

    /** RFC 8058: mail clients POST here when the user clicks "unsubscribe". */
    public function oneClick(Request $request, SavedSearch $savedSearch): HttpResponse
    {
        $savedSearch->forceFill(['email' => false])->save();

        return response('', 204);
    }
}
