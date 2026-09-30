@extends('emails.layout')

@section('title', 'Restablece tu contraseña')
@section('preheader', 'Crea una contraseña nueva para tu cuenta en '.config('app.name').'.')
@section('kicker', 'RESTABLECER CONTRASEÑA')
@section('headline')
    Vuelve a<br><span style="color:#abb994;">entrar.</span>
@endsection
@section('intro', 'Recibimos una solicitud para restablecer la contraseña de tu cuenta. Usa el botón para crear una nueva.')
@section('button', 'Crear nueva contraseña')
@section('disclaimer', 'Si no lo solicitaste, no necesitas hacer nada: tu contraseña actual sigue siendo la misma.')
