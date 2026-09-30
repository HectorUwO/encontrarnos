Nueva cuenta en {{ config('app.name') }}

Nombre: {{ $userName }}
Correo: {{ $userEmail }}
Fecha: {{ $registeredAt }}
Cuentas en total: {{ number_format($totalUsers) }}

Ver en el panel:
{{ $url }}

Aún debe verificar su correo para usar la cuenta.
