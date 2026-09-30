<?php

namespace App\Http\Controllers;

use App\Http\Requests\OfferInformationRequest;
use App\Mail\NoticeMail;
use App\Models\InformationReport;
use App\Models\PersonRecord;
use App\Services\AdminNotifier;
use Illuminate\Http\RedirectResponse;

class PersonRecordInformationController extends Controller
{
    /**
     * Quien tiene datos sobre una persona desaparecida escribe desde su ficha.
     * Las fichas vienen de un registro nacional y no tienen contacto propio:
     * el mensaje se guarda y le llega a quien administra, que lo canaliza con
     * la autoridad responsable.
     */
    public function store(OfferInformationRequest $request, PersonRecord $personRecord, AdminNotifier $notifier): RedirectResponse
    {
        abort_unless($personRecord->published_at !== null, 404);

        $sender = $request->user();
        $message = $request->validated('message');
        $phone = $request->validated('phone');

        InformationReport::create([
            'user_id' => $sender->id,
            'person_record_id' => $personRecord->id,
            'message' => $message,
            'phone' => $phone,
        ]);

        $notifier->send(new NoticeMail(
            noticeSubject: 'Información sobre la ficha '.$personRecord->folio,
            kicker: 'FICHA '.$personRecord->folio,
            headline: 'Alguien tiene|información.',
            paragraphs: array_values(array_filter([
                $sender->name.' ('.$sender->email.($phone ? ', '.$phone : '').') escribió sobre '.($personRecord->name ?? 'la ficha '.$personRecord->folio).':',
                $message,
                $personRecord->authority ? 'Autoridad que registró la ficha: '.$personRecord->authority.'.' : null,
            ])),
            buttonLabel: 'Ver información recibida',
            buttonUrl: route('admin.information'),
        ));

        return back()->with('status', 'information-sent');
    }
}
