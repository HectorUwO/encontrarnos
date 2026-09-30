@extends('emails.layout')

@section('title', 'Nueva cuenta')
@section('preheader', $userName.' ('.$userEmail.') creó una cuenta.')
@section('kicker', 'NUEVA CUENTA')
@section('headline')
    Alguien se<br><span style="color:#abb994;">unió.</span>
@endsection
@section('intro')
    <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:14px;font-size:15px;line-height:1.6;color:#c9cfc0;">
        <tr>
            <td style="padding:2px 16px 2px 0;color:#8a9087;">Nombre</td>
            <td style="padding:2px 0;color:#f0ecdf;font-weight:700;">{{ $userName }}</td>
        </tr>
        <tr>
            <td style="padding:2px 16px 2px 0;color:#8a9087;">Correo</td>
            <td style="padding:2px 0;color:#f0ecdf;font-weight:700;">{{ $userEmail }}</td>
        </tr>
        <tr>
            <td style="padding:2px 16px 2px 0;color:#8a9087;">Fecha</td>
            <td style="padding:2px 0;color:#f0ecdf;">{{ $registeredAt }}</td>
        </tr>
        <tr>
            <td style="padding:2px 16px 2px 0;color:#8a9087;">Cuentas</td>
            <td style="padding:2px 0;color:#f0ecdf;">{{ number_format($totalUsers) }} en total</td>
        </tr>
    </table>
@endsection
@section('button', 'Ver en el panel')
@section('disclaimer', 'Aún debe verificar su correo para usar la cuenta.')
