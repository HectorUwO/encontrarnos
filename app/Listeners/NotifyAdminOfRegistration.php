<?php

namespace App\Listeners;

use App\Mail\NewUserMail;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyAdminOfRegistration
{
    public function handle(Registered $event): void
    {
        $to = config('mail.admin_notification');

        if (! $to || ! $event->user instanceof User) {
            return;
        }

        // Un fallo del aviso nunca debe impedir que la persona se registre.
        try {
            Mail::to($to)->send(new NewUserMail($event->user));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
