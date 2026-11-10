@php
    // These pages can render when nothing else works (no session, no database): decide the language only from what is at hand.
    $supported = ['es', 'en', 'pt', 'fr', 'it', 'de'];
    try {
        $locale = auth()->user()?->locale;
    } catch (\Throwable) {
        $locale = null;
    }
    app()->setLocale(in_array($locale, $supported, true) ? $locale : ($supported[array_search(request()->getPreferredLanguage($supported), $supported, true)] ?? config('app.locale')));

@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $status }} · {{ __("errors.{$status}.title") }} · {{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.svg?v=4" type="image/svg+xml">
    <style>
        :root { color-scheme: light dark; --bg: #F7F8F5; --fg: #24332D; --muted: #66736C; --brand: #292D32; --on-brand: #FAF8F6; --line: #E2E8E3; }
        @media (prefers-color-scheme: dark) { :root { --bg: #111A17; --fg: #F1F5F1; --muted: #B0BFB5; --brand: #F47761; --on-brand: #292D32; --line: #35473D; } }
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
        <svg viewBox="0 0 64 64" role="img" aria-label="{{ config('app.name') }}">
            <rect width="64" height="64" rx="14" fill="#292D32"/>
            <path d="m32 9 20 11-20 11-20-11L32 9Zm-20 21 20 11 20-11M12 40l20 11 20-11" fill="none" stroke="#F47761" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
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
