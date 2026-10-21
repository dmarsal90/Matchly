@php
    // These pages can render when nothing else works (no session, no database): decide the language only from what is at hand.
    $supported = ['es', 'en', 'pt', 'fr', 'it', 'de'];
    try {
        $locale = auth()->user()?->locale;
    } catch (\Throwable) {
        $locale = null;
    }
    app()->setLocale(in_array($locale, $supported, true) ? $locale : ($supported[array_search(request()->getPreferredLanguage($supported), $supported, true)] ?? config('app.locale')));

    $circles = match ($mark ?? 'match') {
        'apart' => ['a' => 15, 'b' => 49, 'lens' => null],
        'faded' => ['a' => 24, 'b' => 40, 'lens' => '#9AA8A3'],
        'broken' => ['a' => 24, 'b' => 40, 'lens' => '#C2410C'],
        default => ['a' => 24, 'b' => 40, 'lens' => '#F4A62A'],
    };
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $status }} · {{ __("errors.{$status}.title") }} · {{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.svg?v=3" type="image/svg+xml">
    <style>
        :root { color-scheme: light dark; --bg: #F5F8F7; --fg: #0E1B19; --muted: #4B5F5A; --brand: #0F3D2E; --on-brand: #F7F3E8; --line: #D6E0DD; --circle: #0F3D2E; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0B1513; --fg: #F1F6F4; --muted: #A9BAB5; --brand: #7FD6C2; --on-brand: #0B1513; --line: #22332F; --circle: #F7F3E8; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100dvh; display: grid; place-items: center; padding: 2rem 1.25rem; background: var(--bg); color: var(--fg); font: 16px/1.55 'Instrument Sans', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
        main { width: 100%; max-width: 34rem; text-align: center; }
        svg { width: min(15rem, 70%); height: auto; margin: 0 auto 1.5rem; display: block; }
        .code { font-size: .9rem; font-weight: 600; color: var(--muted); letter-spacing: .02em; }
        h1 { font-size: clamp(1.75rem, 5vw, 2.4rem); line-height: 1.1; letter-spacing: -.02em; margin: .35rem 0 .75rem; text-wrap: balance; }
        p { margin: 0 auto; max-width: 28rem; color: var(--muted); text-wrap: pretty; }
        .actions { display: flex; flex-wrap: wrap; gap: .75rem; justify-content: center; margin-top: 1.75rem; }
        a { font: inherit; font-weight: 600; padding: .65rem 1.1rem; border-radius: .6rem; text-decoration: none; cursor: pointer; border: 1px solid var(--line); background: transparent; color: var(--fg); }
        a.primary { background: var(--brand); color: var(--on-brand); border-color: var(--brand); }
        a:focus-visible { outline: 3px solid #F4A62A; outline-offset: 2px; }
        footer { margin-top: 2.5rem; font-size: .85rem; color: var(--muted); }
    </style>
</head>
<body>
    <main>
        <svg viewBox="0 0 64 40" role="img" aria-label="{{ config('app.name') }}">
            <circle cx="{{ $circles['a'] }}" cy="20" r="13" fill="var(--circle)"/>
            <circle cx="{{ $circles['b'] }}" cy="20" r="13" fill="var(--circle)"/>
            @if ($circles['lens'])
                <path d="M32 10.72A13 13 0 0 1 32 29.28A13 13 0 0 1 32 10.72Z" fill="{{ $circles['lens'] }}"/>
            @endif
        </svg>
        <p class="code">{{ $status }}</p>
        <h1>{{ __("errors.{$status}.title") }}</h1>
        <p>{{ __("errors.{$status}.text") }}</p>
        <div class="actions">
            <a class="primary" href="/">{{ __('errors.home') }}</a>
            @if ($retry ?? false)
                <a href="{{ request()->fullUrl() }}">{{ __('errors.retry') }}</a>
            @else
                <a href="{{ url()->previous('/') }}">{{ __('errors.back') }}</a>
            @endif
        </div>
        <footer>{{ config('app.name') }}</footer>
    </main>
</body>
</html>
