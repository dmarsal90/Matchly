<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Pulse\Facades\Pulse;

/**
 * Real-user performance beacon. Stores only metric, Inertia page component
 * and value: no IP, user agent or URL, so nothing personal reaches Pulse.
 */
final class WebVitalController
{
    public const METRICS = ['LCP', 'INP', 'CLS', 'TTFB'];

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'metric' => ['required', 'string', 'in:'.implode(',', self::METRICS)],
            'page' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_\/.-]+$/'],
            'value' => ['required', 'numeric', 'min:0', 'max:60000'],
        ]);

        // Pulse stores integers; CLS is a small unitless score, so keep three decimals.
        $value = (int) round((float) $data['value'] * ($data['metric'] === 'CLS' ? 1000 : 1));

        Pulse::record('web_vital', $data['metric'].'|'.$data['page'], $value)->avg()->max()->count();

        return response()->json(status: 204);
    }
}
