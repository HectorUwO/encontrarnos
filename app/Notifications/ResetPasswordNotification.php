<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Restablece tu contraseña · '.config('app.name'))
            ->priority(1)
            ->view(['emails.reset-password', 'emails.text.reset-password'], [
                'url' => $this->resetUrl($notifiable),
                'name' => $notifiable->name ?? null,
                'expire' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
            ]);
    }
}
