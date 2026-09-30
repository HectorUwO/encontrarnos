@extends('emails.layout')

@section('title', $heading)
@section('preheader', $paragraphs[0] ?? $heading)
@section('kicker', 'COMUNICADO')
@section('headline', $heading)
@section('intro')
    @foreach ($paragraphs as $paragraph)
        <p style="margin:{{ $loop->first ? '12px' : '16px' }} 0 0;">{!! nl2br(e($paragraph)) !!}</p>
    @endforeach
@endsection
@section('button', $buttonLabel)
