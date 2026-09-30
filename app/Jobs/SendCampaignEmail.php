<?php

namespace App\Jobs;

use App\Mail\CampaignMail;
use App\Models\MailCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCampaignEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(
        public int $campaignId,
        public string $email,
        public ?string $name = null,
    ) {}

    public function handle(): void
    {
        $campaign = MailCampaign::find($this->campaignId);

        if (! $campaign) {
            return;
        }

        Mail::to($this->email)->send(new CampaignMail($campaign, $this->name));

        $campaign->increment('sent_count');
    }

    public function failed(Throwable $exception): void
    {
        MailCampaign::whereKey($this->campaignId)->increment('failed_count');
    }
}
