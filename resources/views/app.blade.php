<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        @php
            $shareTitle = config('app.name', 'Encontrarnos').' · Búsqueda de personas en México';
            $shareDescription = 'Consulta fichas de personas desaparecidas, comparte información y ayuda a encontrarlas.';
            $shareImage = url('/og-image.png').'?v=2';
        @endphp
        <meta name="description" content="{{ $shareDescription }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name', 'Encontrarnos') }}">
        <meta property="og:locale" content="es_MX">
        <meta property="og:title" content="{{ $shareTitle }}">
        <meta property="og:description" content="{{ $shareDescription }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:image" content="{{ $shareImage }}">
        <meta property="og:image:type" content="image/png">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="Encontrarnos. Hasta encontrarnos · Búsqueda de personas en México">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $shareTitle }}">
        <meta name="twitter:description" content="{{ $shareDescription }}">
        <meta name="twitter:image" content="{{ $shareImage }}">

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
