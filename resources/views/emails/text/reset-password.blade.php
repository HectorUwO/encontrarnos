{{ $name ? 'Hola, '.$name.'.' : 'Hola.' }}

Recibimos una solicitud para restablecer la contraseña de tu cuenta en {{ config('app.name') }}.

Crear nueva contraseña:
{{ $url }}

El enlace vence en {{ $expire }} minutos. Si no lo solicitaste, no necesitas hacer nada: tu contraseña actual sigue siendo la misma.

Por quienes faltan. Con quienes buscan.
{{ config('app.name') }} · México
