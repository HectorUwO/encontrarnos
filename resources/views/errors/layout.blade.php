@php
    $page = config("errors.pages.$status", config('errors.fallbacks.'.($status < 500 ? '4xx' : '5xx')));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $status }} · {{ $page['title'] }} — Encontrarnos</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('errors.css') }}">
</head>
<body class="error-page">
    <a class="error-skip" href="#contenido">Saltar al contenido</a>
    <header class="error-header">
        <a class="error-brand" href="{{ url('/') }}" aria-label="Encontrarnos, ir al inicio">
            <img src="{{ asset('apple-touch-icon.png') }}" width="42" height="42" alt="">
            <span>encontrarnos<span class="error-period">.</span></span>
        </a>
        <span class="error-header-note">UNA PLATAFORMA PARA SEGUIR BUSCANDO</span>
    </header>
    <main class="error-main" id="contenido">
        <div class="error-visual" aria-hidden="true">
            <span class="error-orbit"></span>
            <span class="error-code">{{ $status }}</span>
            <span class="error-visual-caption">ENCONTRARNOS / {{ $status }}</span>
        </div>
        <section class="error-content" aria-labelledby="error-title">
            <p class="error-eyebrow">UN ALTO EN EL CAMINO <span>— ERROR {{ $status }}</span></p>
            <h1 id="error-title">{{ $page['title'] }}</h1>
            <p class="error-description">{{ $page['description'] }}</p>
            <nav class="error-actions" aria-label="Opciones para continuar">
                @if ($status === 401)
                    <a class="error-button" href="{{ route('login') }}">Iniciar sesión <span aria-hidden="true">↗</span></a>
                @else
                    <a class="error-button" href="{{ url('/') }}">Volver al inicio <span aria-hidden="true">↗</span></a>
                @endif
                <a class="error-secondary" href="{{ route('records') }}">Consultar la base de datos <span aria-hidden="true">→</span></a>
            </nav>
            <p class="error-help">La búsqueda continúa. Gracias por ser parte.</p>
        </section>
    </main>
    <footer class="error-footer">
        <span>QUE LA BÚSQUEDA NO SE DETENGA.</span>
        <span>Encontrarnos · México</span>
    </footer>
</body>
</html>
