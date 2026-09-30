{{ mb_strtoupper(trim($headlineFirst.' '.$headlineSecond)) }}

@foreach ($paragraphs as $paragraph)
{{ $paragraph }}

@endforeach
@if ($url)
{{ $buttonLabel }}:
{{ $url }}

@endif
@if ($footnote)
{{ $footnote }}

@endif
{{ config('app.name') }} · México
