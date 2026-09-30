<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso para administración: alguien creó una cuenta.
 */
class NewUserMail extends Mailable
{
    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nueva cuenta · '.$this->user->name);
    }

    public function content(): Content
    {
        $data = [
            'name' => null,
            'noGreeting' => true,
            'url' => route('admin.users', ['q' => $this->user->email]),
            'expire' => null,
            'userName' => $this->user->name,
            'userEmail' => $this->user->email,
            'registeredAt' => $this->user->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            'totalUsers' => User::count(),
        ];

        return new Content(
            view: 'emails.new-user',
            text: 'emails.text.new-user',
            with: $data,
        );
    }
}
