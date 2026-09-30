@extends('emails.layout')

@section('title', 'Información para tu solicitud')
@section('preheader', $senderName.' compartió información sobre '.$subjectName.'.')
@section('kicker', 'SOLICITUD '.$reference)
@section('headline')
    Alguien quiere<br><span style="color:#abb994;">ayudar.</span>
@endsection
@section('intro')
    <p style="margin:12px 0 0;">
        <strong style="color:#f0ecdf;">{{ $senderName }}</strong> compartió información sobre {{ $subjectName }}:
    </p>
    <div style="margin:16px 0 0;padding:14px 18px;border-left:3px solid #d0b371;background-color:#181818;color:#e4e7dc;">
        @foreach ($paragraphs as $paragraph)
            <p style="margin:{{ $loop->first ? '0' : '12px' }} 0 0;">{!! nl2br(e($paragraph)) !!}</p>
        @endforeach
    </div>
    <p style="margin:16px 0 0;font-size:14px;color:#8a9087;">
        Correo: <span style="color:#f0ecdf;">{{ $senderEmail }}</span>
        @if ($phone)
            · Teléfono: <span style="color:#f0ecdf;">{{ $phone }}</span>
        @endif
    </p>
@endsection
@section('button', $buttonLabel)
@section('disclaimer', 'Verifica la información antes de actuar. Si tienes dudas, acude a la autoridad correspondiente.')
