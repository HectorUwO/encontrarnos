<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso genérico con la plantilla de la plataforma: un título de dos líneas
 * (separadas por «|»), párrafos y, si hace falta, un botón.
 */
class NoticeMail extends Mailable
{
    /**
     * @param  list<string>  $paragraphs
     */
    public function __construct(
        public string $noticeSubject,
        public string $kicker,
        public string $headline,
        public array $paragraphs,
        public ?string $buttonLabel = null,
        public ?string $buttonUrl = null,
        public ?string $footnote = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->noticeSubject);
    }

    public function content(): Content
    {
        [$first, $second] = array_pad(explode('|', $this->headline, 2), 2, null);

        return new Content(
            view: 'emails.notice',
            text: 'emails.text.notice',
            with: [
                'name' => null,
                'noGreeting' => true,
                'url' => $this->buttonUrl,
                'expire' => null,
                'kicker' => $this->kicker,
                'headlineFirst' => $first,
                'headlineSecond' => $second,
                'paragraphs' => $this->paragraphs,
                'buttonLabel' => $this->buttonLabel ?? 'Abrir',
                'footnote' => $this->footnote,
            ],
        );
    }
}
