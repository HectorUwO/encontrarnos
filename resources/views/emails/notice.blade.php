@extends('emails.layout')

@section('title', strip_tags($headlineFirst.' '.$headlineSecond))
@section('preheader', $paragraphs[0] ?? $headlineFirst)
@section('kicker', $kicker)
@section('headline')
    {{ $headlineFirst }}@if ($headlineSecond)<br><span style="color:#abb994;">{{ $headlineSecond }}</span>@endif
@endsection
@section('intro')
    @foreach ($paragraphs as $paragraph)
        <p style="margin:{{ $loop->first ? '12px' : '14px' }} 0 0;">{!! nl2br(e($paragraph)) !!}</p>
    @endforeach
@endsection
@section('button', $buttonLabel)
@if ($footnote)
    @section('disclaimer', $footnote)
@endif
