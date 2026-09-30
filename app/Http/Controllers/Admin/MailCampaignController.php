<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMailCampaignRequest;
use App\Jobs\SendCampaignEmail;
use App\Mail\CampaignMail;
use App\Models\MailCampaign;
use App\Services\CampaignAudience;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class MailCampaignController extends Controller
{
    /** Resend permite ~2 envíos por segundo; se reparten en el tiempo. */
    private const PER_SECOND = 2;

    public function index(CampaignAudience $audience): Response
    {
        return Inertia::render('Admin/Mail', [
            'counts' => $audience->counts(),
            'campaigns' => MailCampaign::query()
                ->with('sender:id,name')
                ->latest()
                ->latest('id')
                ->limit(15)
                ->get()
                ->map(fn (MailCampaign $campaign): array => [
                    'id' => $campaign->id,
                    'subject' => $campaign->subject,
                    'audience' => $campaign->audience,
                    'recipients' => $campaign->recipients_count,
                    'sent' => $campaign->sent_count,
                    'failed' => $campaign->failed_count,
                    'sender' => $campaign->sender?->name,
                    'created_at' => $campaign->created_at?->format('d/m/Y H:i'),
                ]),
        ]);
    }

    /**
     * Devuelve el HTML del correo tal como lo verán las personas.
     */
    public function preview(StoreMailCampaignRequest $request): JsonResponse
    {
        $campaign = $this->makeCampaign($request);

        return response()->json([
            'html' => view('emails.campaign', CampaignMail::viewData($campaign, $request->user()->name))->render(),
        ]);
    }

    /**
     * Envía el correo solo a quien lo redacta, para revisarlo antes de la campaña.
     */
    public function test(StoreMailCampaignRequest $request): RedirectResponse
    {
        $campaign = $this->makeCampaign($request);

        Mail::to($request->user()->email)->send(new CampaignMail($campaign, $request->user()->name));

        return back()->with('status', 'test-sent');
    }

    public function store(StoreMailCampaignRequest $request, CampaignAudience $audience): RedirectResponse
    {
        $recipients = $audience->recipients($request->input('audience'), $request->customEmails());

        if ($recipients->isEmpty()) {
            return back()->withErrors(['audience' => 'No hay personas a las que enviar este correo.']);
        }

        $campaign = $this->makeCampaign($request);
        $campaign->recipients_count = $recipients->count();
        $campaign->save();

        foreach ($recipients as $index => $recipient) {
            SendCampaignEmail::dispatch($campaign->id, $recipient['email'], $recipient['name'])
                ->delay(now()->addSeconds(intdiv($index, self::PER_SECOND)));
        }

        return redirect()->route('admin.mail')->with('status', 'campaign-queued');
    }

    private function makeCampaign(StoreMailCampaignRequest $request): MailCampaign
    {
        return new MailCampaign([
            'user_id' => $request->user()->id,
            'subject' => $request->input('subject'),
            'heading' => $request->input('heading'),
            'body' => $request->input('body'),
            'button_label' => $request->input('button_label'),
            'button_url' => $request->input('button_url'),
            'audience' => $request->input('audience'),
        ]);
    }
}
