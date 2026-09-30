Alguien compartió información para tu solicitud {{ $reference }}

De: {{ $senderName }} <{{ $senderEmail }}>
@if ($phone)
Teléfono: {{ $phone }}
@endif

@foreach ($paragraphs as $paragraph)
{{ $paragraph }}

@endforeach
Puedes responder directamente a este correo.

Verifica la información antes de actuar. Si tienes dudas, acude a la autoridad correspondiente.

{{ config('app.name') }} · México
