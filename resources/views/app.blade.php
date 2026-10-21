<!DOCTYPE html>
{{-- The public pages, the showcase and the welcome page, keep their own light look, whatever the visitor's theme --}}
@php($keepsLightLook = ($isShowcase ?? false) || ($isWelcome ?? false))
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ! $keepsLightLook && ($appearance ?? 'system') == 'dark']) @if ($isShowcase ?? false) data-showcase @endif @if ($keepsLightLook) data-light-look @endif>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @if ($isShowcase ?? false)
            <link rel="preconnect" href="https://fonts.bunny.net">
            <link rel="stylesheet" href="https://fonts.bunny.net/css?family=sora:500,600,700|figtree:400,500,600,700&display=swap">
        @endif

        @isset($meta)
            <meta name="description" content="{{ $meta['description'] }}">
            <meta property="og:type" content="website">
            <meta property="og:locale" content="pt_BR">
            <meta property="og:title" content="{{ $meta['title'] }}">
            <meta property="og:description" content="{{ $meta['description'] }}">
            <meta property="og:url" content="{{ $meta['url'] }}">
            <link rel="canonical" href="{{ $meta['url'] }}">
            @if ($meta['image'])
                <meta property="og:image" content="{{ $meta['image'] }}">
                <meta name="twitter:card" content="summary_large_image">
            @endif
        @endisset

        @unless ($keepsLightLook)
        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script nonce="{{ Vite::cspNonce() }}">
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>
        @endunless

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        {{-- The showcase styles go in the head, so its pages never show before them --}}
        @vite([
            'resources/css/app.css',
            ...(($isShowcase ?? false) ? ['resources/css/showcase.css'] : []),
            'resources/js/app.ts',
            "resources/js/pages/{$page['component']}.vue",
        ])
        <x-inertia::head>
            <title>{{ $meta['title'] ?? config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
