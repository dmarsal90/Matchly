<?php

declare(strict_types=1);

namespace App\Livewire\Pulse;

use App\Modules\Shared\Http\Controllers\WebVitalController;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

#[Lazy]
final class WebVitals extends Card
{
    /** Google's "good" / "poor" boundaries (ms; CLS is stored x1000). */
    private const THRESHOLDS = ['LCP' => [2500, 4000], 'INP' => [200, 500], 'CLS' => [100, 250], 'TTFB' => [800, 1800]];

    public function render(): Renderable
    {
        [$vitals, $time, $runAt] = $this->remember(fn () => $this->aggregate('web_vital', ['avg', 'max', 'count'], 'count')->map(function ($row): object {
            [$metric, $page] = explode('|', $row->key, 2);
            [$good, $poor] = self::THRESHOLDS[$metric] ?? [PHP_INT_MAX, PHP_INT_MAX];

            return (object) [
                'metric' => $metric,
                'page' => $page,
                'avg' => (int) $row->avg,
                'max' => (int) $row->max,
                'count' => (int) $row->count,
                'status' => $row->avg <= $good ? 'good' : ($row->avg <= $poor ? 'warn' : 'poor'),
                'unit' => $metric === 'CLS' ? '' : 'ms',
                'scale' => $metric === 'CLS' ? 1000 : 1,
            ];
        })->sortBy(['metric', 'page'])->values());

        return View::make('livewire.pulse.web-vitals', ['vitals' => $vitals, 'time' => $time, 'runAt' => $runAt, 'metrics' => WebVitalController::METRICS]);
    }
}
