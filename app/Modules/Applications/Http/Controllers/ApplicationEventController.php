<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Controllers;

use App\Modules\Applications\Application\Actions\LogApplicationEvent;
use App\Modules\Applications\Http\Requests\StoreApplicationEventRequest;
use App\Modules\Applications\Infrastructure\Models\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class ApplicationEventController
{
    public function store(StoreApplicationEventRequest $request, Application $application, LogApplicationEvent $log): RedirectResponse
    {
        Gate::authorize('update', $application);

        $log->handle($application, $request->type(), $request->note(), $request->occurredAt());

        return back();
    }
}
