@extends('emails.layout')

@section('title', 'Verifica tu correo')
@section('preheader', 'Confirma tu correo para activar tu cuenta en '.config('app.name').'.')
@section('kicker', 'VERIFICACIÓN DE CORREO')
@section('headline')
    Un paso más<br><span style="color:#abb994;">para buscar.</span>
@endsection
@section('intro', 'Confirma tu correo para activar tu cuenta y empezar a consultar, compartir y dar seguimiento a las búsquedas.')
@section('button', 'Verificar mi correo')
@section('disclaimer', 'Si no creaste una cuenta, puedes ignorar este mensaje.')
