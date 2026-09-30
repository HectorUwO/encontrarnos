{{ $name ? 'Hola, '.$name.'.' : 'Hola.' }}

{{ mb_strtoupper($heading) }}

@foreach ($paragraphs as $paragraph)
{{ $paragraph }}

@endforeach
@if ($url)
{{ $buttonLabel }}:
{{ $url }}

@endif
Por quienes faltan. Con quienes buscan.
{{ config('app.name') }} · México
