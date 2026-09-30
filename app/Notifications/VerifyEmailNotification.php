<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verifica tu correo · '.config('app.name'))
            ->priority(1)
            ->view(['emails.verify-email', 'emails.text.verify-email'], [
                'url' => $this->verificationUrl($notifiable),
                'name' => $notifiable->name ?? null,
                'expire' => config('auth.verification.expire', 60),
            ]);
    }
}
