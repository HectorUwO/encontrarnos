<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="48x48">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @if (str_ends_with(request()->getHost(), '.trycloudflare.com'))
            @php
                Vite::useHotFile('');
            @endphp
        @endif

        <!-- Fonts: propias, se piden en paralelo con el CSS en lugar de esperar a que se descubran en él -->
        @foreach (['barlow-condensed-800', 'manrope-800'] as $font)
            <link rel="preload" href="{{ Vite::asset("public/fonts/{$font}.woff2") }}" as="font" type="font/woff2" crossorigin>
        @endforeach

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
