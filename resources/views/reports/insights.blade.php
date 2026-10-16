@php
    /**
     * Self-contained on purpose: no scripts, no external styles, fonts or images,
     * so the file opens the same offline, years later, and can be shared as is.
     *
     * @var array<string, mixed> $funnel
     * @var array<string, mixed> $gaps
     */
    $category = fn (string $name): string => $name === 'other' ? __('insights.funnel.other') : __("jobs.category.{$name}");
    $outcomes = array_filter($funnel['outcomes'], fn (int $count): bool => $count > 0);
    $calibration = $funnel['calibration'];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('insights.report.title') }}@if ($name) · {{ $name }}@endif</title>
    <style>
        :root { color-scheme: light dark; --fg: #1a1a1a; --muted: #5c5c5c; --line: #d6d6d6; --soft: #f4f4f5; --link: #1d4ed8; }
        @media (prefers-color-scheme: dark) { :root { --fg: #ececec; --muted: #a3a3a3; --line: #3a3a3a; --soft: #1f1f1f; --link: #8ab4ff; } body { background: #111; } }
        body { font: 16px/1.5 system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; color: var(--fg); max-width: 52rem; margin: 0 auto; padding: 1.5rem 1rem 3rem; }
        h1 { font-size: 1.6rem; margin: 0 0 .25rem; }
        h2 { font-size: 1.2rem; margin: 2rem 0 .5rem; padding-bottom: .25rem; border-bottom: 1px solid var(--line); }
        h3 { font-size: 1rem; margin: 1.25rem 0 .25rem; }
        a { color: var(--link); }
        .muted { color: var(--muted); font-size: .9rem; }
        table { border-collapse: collapse; width: 100%; margin: .5rem 0 1rem; font-size: .95rem; }
        th, td { text-align: left; padding: .35rem .5rem; border-bottom: 1px solid var(--line); }
        td.n, th.n { text-align: right; font-variant-numeric: tabular-nums; }
        .card { background: var(--soft); border-radius: .5rem; padding: .75rem 1rem; margin: .75rem 0; }
        ul { padding-left: 1.25rem; margin: .25rem 0; }
        .tag { display: inline-block; font-size: .75rem; border: 1px solid var(--line); border-radius: 999px; padding: 0 .5rem; margin-right: .25rem; }
        @media print { body { max-width: none; padding: 0; } a { color: inherit; } }
    </style>
</head>
<body>
    <h1>{{ __('insights.report.title') }}</h1>
    <p class="muted">@if ($name){{ $name }} · @endif{{ __('insights.report.generated', ['date' => $generatedAt]) }}</p>
    <p class="muted">{{ __('insights.report.offline') }}</p>

    <h2>{{ __('insights.funnel.title') }}</h2>
    @if ($funnel['applied'] === 0)
        <p>{{ __('insights.funnel.empty') }}</p>
    @else
        <table>
            <caption class="muted" style="text-align:left">{{ __('insights.funnel.stages_table') }}</caption>
            <thead><tr><th>{{ __('insights.funnel.stage') }}</th><th class="n">{{ __('insights.funnel.count') }}</th></tr></thead>
            <tbody>
                @foreach ($funnel['stages'] as $stage)
                    <tr><td>{{ __("insights.funnel.stages.{$stage['key']}") }}</td><td class="n">{{ $stage['count'] }}</td></tr>
                @endforeach
            </tbody>
        </table>

        <ul>
            @foreach ($funnel['conversion'] as $step)
                <li>
                    @if ($step['rate'] !== null)
                        {{ __('insights.funnel.conversion', ['from' => __("insights.funnel.stages.{$step['from']}"), 'to' => __("insights.funnel.stages.{$step['to']}"), 'rate' => $step['rate']]) }}
                    @else
                        {{ __('insights.funnel.conversion_none', ['from' => __("insights.funnel.stages.{$step['from']}"), 'to' => __("insights.funnel.stages.{$step['to']}")]) }}
                    @endif
                </li>
            @endforeach
        </ul>

        @if ($funnel['timing']['first_response_days'] !== null)
            <p>{{ __('insights.funnel.timing_response', ['days' => $funnel['timing']['first_response_days']]) }}</p>
            @if ($funnel['timing']['first_interview_days'] !== null)
                <p>{{ __('insights.funnel.timing_interview', ['days' => $funnel['timing']['first_interview_days']]) }}</p>
            @endif
        @else
            <p>{{ __('insights.funnel.timing_none') }}</p>
        @endif

        @if ($calibration['interview_score'] !== null && $calibration['closed_score'] !== null)
            <div class="card">
                <strong>{{ __('insights.funnel.calibration.title') }}</strong>
                <p>{{ __('insights.funnel.calibration.summary', ['interview' => $calibration['interview_score'], 'closed' => $calibration['closed_score']]) }}</p>
            </div>
        @endif

        <h3>{{ __('insights.funnel.outcomes') }}</h3>
        <ul>
            @foreach ($outcomes as $key => $count)
                <li>{{ __("insights.funnel.outcome.{$key}") }}: {{ $count }}</li>
            @endforeach
        </ul>

        @foreach (['by_source' => 'insights.funnel.by_source', 'by_category' => 'insights.funnel.by_category'] as $group => $label)
            @if (count($funnel[$group]) > 0)
                <h3>{{ __($label) }}</h3>
                <table>
                    <thead><tr><th>{{ __('insights.funnel.name') }}</th><th class="n">{{ __('insights.funnel.applied') }}</th><th class="n">{{ __('insights.funnel.interviews') }}</th><th class="n">{{ __('insights.funnel.offers') }}</th></tr></thead>
                    <tbody>
                        @foreach ($funnel[$group] as $row)
                            <tr><td>{{ $group === 'by_category' ? $category($row['name']) : $row['name'] }}</td><td class="n">{{ $row['applied'] }}</td><td class="n">{{ $row['interviews'] }}</td><td class="n">{{ $row['offers'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endforeach

        @if ($funnel['applied'] < 10)
            <p class="muted">{{ __('insights.funnel.small_sample') }}</p>
        @endif
    @endif

    @if (count($experiments) > 0)
        <h2>{{ __('insights.experiments.title') }}</h2>
        <ul>
            @foreach ($experiments as $idea)
                <li>{{ __("insights.experiments.{$idea['key']}", $idea['params']) }}</li>
            @endforeach
        </ul>
    @endif

    <h2>{{ __('insights.skill_gaps.title') }}</h2>
    @if (count($gaps['skills']) === 0)
        <p>{{ __('insights.skill_gaps.empty') }}</p>
    @else
        <p class="muted">{{ __('insights.skill_gaps.basis', ['jobs' => $gaps['jobs'], 'tracked' => $gaps['tracked']]) }}</p>
        <table>
            <thead><tr><th>{{ __('insights.skill_gaps.skill') }}</th><th class="n">{{ __('insights.skill_gaps.jobs') }}</th><th class="n">{{ __('insights.skill_gaps.required') }}</th><th class="n">{{ __('insights.skill_gaps.only_gap') }}</th><th class="n">{{ __('insights.report.hours') }}</th></tr></thead>
            <tbody>
                @foreach ($gaps['skills'] as $skill)
                    <tr><td>{{ $skill['name'] }}</td><td class="n">{{ $skill['jobs'] }}</td><td class="n">{{ $skill['required'] }}</td><td class="n">{{ $skill['only_gap'] }}</td><td class="n">≈ {{ $skill['hours'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
        <p class="muted">{{ __('insights.report.hours_note') }}</p>

        <h3>{{ __('insights.report.courses') }}</h3>
        <ul>
            @foreach ($gaps['skills'] as $skill)
                @php($free = array_values(array_filter($skill['courses'], fn (array $course): bool => $course['price'] === 'free' && $course['curated'])))
                @if ($free !== [])
                    <li>
                        <strong>{{ $skill['name'] }}</strong>:
                        @foreach ($free as $course)
                            <a href="{{ $course['url'] }}" rel="noopener noreferrer nofollow">{{ $course['title'] }}</a> <span class="muted">({{ $course['provider'] }})</span>@if (! $loop->last) · @endif
                        @endforeach
                    </li>
                @endif
            @endforeach
        </ul>
    @endif

    @if (count($gaps['closest']) > 0)
        <h2>{{ __('insights.closest.title') }}</h2>
        @foreach ($gaps['closest'] as $job)
            <div class="card">
                <strong>{{ $job['title'] }} · {{ $job['company'] }}</strong>
                <span class="muted"> — {{ __('insights.closest.score', ['score' => $job['score']]) }}</span>
                <ul>
                    @foreach ($job['missing'] as $skill)
                        <li>{{ $skill['name'] }} <span class="tag">{{ $skill['required'] ? __('insights.closest.required') : __('insights.closest.nice') }}</span> <span class="muted">≈ {{ $skill['hours'] }} h</span></li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    @endif

    <p class="muted" style="margin-top:2rem">{{ config('app.name') }}</p>
</body>
</html>
