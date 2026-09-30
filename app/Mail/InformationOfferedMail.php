<?php

namespace App\Mail;

use App\Models\PersonRequest;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso al contacto de una solicitud: alguien compartió información.
 */
class InformationOfferedMail extends Mailable
{
    public function __construct(
        public PersonRequest $personRequest,
        public User $sender,
        public string $body,
        public ?string $phone = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->sender->email, $this->sender->name)],
            subject: 'Información para tu solicitud '.$this->personRequest->reference(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.information-offered',
            text: 'emails.text.information-offered',
            with: [
                'name' => null,
                'noGreeting' => true,
                'url' => 'mailto:'.$this->sender->email,
                'expire' => null,
                'reference' => $this->personRequest->reference(),
                'subjectName' => $this->personRequest->name ?: 'la persona de tu solicitud',
                'senderName' => $this->sender->name,
                'senderEmail' => $this->sender->email,
                'phone' => $this->phone,
                'paragraphs' => CampaignMail::paragraphs($this->body),
                'buttonLabel' => 'Responder',
            ],
        );
    }
}
