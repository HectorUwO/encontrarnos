<?php

namespace App\Mail;

use App\Models\MailCampaign;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CampaignMail extends Mailable
{
    public function __construct(
        public MailCampaign $campaign,
        public ?string $recipientName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->campaign->subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.campaign',
            text: 'emails.text.campaign',
            with: self::viewData($this->campaign, $this->recipientName),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function viewData(MailCampaign $campaign, ?string $name): array
    {
        return [
            'name' => $name,
            'heading' => $campaign->heading,
            'paragraphs' => self::paragraphs($campaign->body),
            'url' => $campaign->button_url ?: null,
            'buttonLabel' => $campaign->button_label ?: 'Abrir',
            'expire' => null,
        ];
    }

    /**
     * @return list<string>
     */
    public static function paragraphs(string $body): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R{2,}/', $body) ?: []),
            fn (string $paragraph): bool => $paragraph !== '',
        ));
    }
}
