<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Controllers;

use App\Modules\Shared\Http\Middleware\SetLocale;
use App\Modules\Shared\Http\Requests\UpdateLocaleRequest;
use Illuminate\Http\RedirectResponse;

final class LocaleController
{
    public function update(UpdateLocaleRequest $request): RedirectResponse
    {
        $request->session()->put(SetLocale::SESSION_KEY, $request->locale());

        return back();
    }
}
