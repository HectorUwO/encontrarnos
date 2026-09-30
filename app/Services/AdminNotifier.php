<?php

namespace App\Services;

use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Avisos por correo para quien administra la plataforma. Un fallo al enviar
 * se registra pero nunca interrumpe lo que la persona estaba haciendo.
 */
class AdminNotifier
{
    public function send(Mailable $mailable): void
    {
        $to = config('mail.admin_notification');

        if (! $to) {
            return;
        }

        $this->attempt(fn () => Mail::to($to)->send($mailable));
    }

    /**
     * Envía un correo a cualquier destinatario con la misma tolerancia a fallos.
     */
    public function attempt(callable $send): void
    {
        try {
            $send();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
