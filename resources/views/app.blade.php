<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="application-name" content="{{ config('app.name') }}">
        <meta name="description" content="{{ __('meta.description', ['app' => config('app.name')]) }}">
        <meta property="og:site_name" content="{{ config('app.name') }}">

        {{-- Applies the dark class before first paint to avoid a flash of the wrong theme --}}
        <script @if (Vite::cspNonce()) nonce="{{ Vite::cspNonce() }}" @endif>
            (function () {
                const appearance = @json($appearance ?? 'system');

                if (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        {{-- Mirrors --background from app.css so the page never flashes white in dark mode --}}
        <style>
            html {
                background-color: hsl(158 17% 96.5%);
            }

            html.dark {
                background-color: hsl(170 30% 6%);
            }
        </style>

        <link rel="icon" href="/favicon.ico?v=2" sizes="any">
        <link rel="icon" href="/favicon.svg?v=2" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=2">
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#10463F" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0B1513" media="(prefers-color-scheme: dark)">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
