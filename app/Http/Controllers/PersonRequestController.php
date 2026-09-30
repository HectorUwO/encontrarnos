<?php

namespace App\Http\Controllers;

use App\Enums\AgeRange;
use App\Enums\MexicanState;
use App\Enums\PersonRequestStatus;
use App\Enums\PersonRequestType;
use App\Enums\Sex;
use App\Http\Requests\IndexPersonRequestsRequest;
use App\Http\Requests\OfferInformationRequest;
use App\Http\Requests\StorePersonRequestRequest;
use App\Http\Resources\PersonRequestResource;
use App\Mail\InformationOfferedMail;
use App\Mail\NoticeMail;
use App\Models\InformationReport;
use App\Models\PersonRecord;
use App\Models\PersonRequest;
use App\Services\AdminNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class PersonRequestController extends Controller
{
    private const PER_PAGE = 12;

    /**
     * Catálogo público: solo solicitudes ya revisadas y aprobadas.
     */
    public function index(IndexPersonRequestsRequest $request): Response
    {
        $requests = PersonRequest::query()
            ->published()
            ->search($request->searchTerm())
            ->inState($request->state())
            ->inAgeRange($request->ageRange())
            ->ofType($request->requestType())
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Public/Solicitudes', [
            'requests' => PersonRequestResource::collection($requests),
            'totalPublished' => PersonRequest::published()->count(),
            'filters' => $request->filters(),
            'options' => [
                'states' => MexicanState::options(),
                'ageRanges' => AgeRange::options(),
                'types' => PersonRequestType::options(),
            ],
        ]);
    }

    /**
     * Formulario para crear una solicitud de búsqueda o de identificación.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Public/SolicitudCrear', [
            'initialType' => $request->enum('tipo', PersonRequestType::class)?->value
                ?? PersonRequestType::Search->value,
            'options' => [
                'states' => MexicanState::options(),
                'sexes' => Sex::options(),
                'types' => PersonRequestType::options(),
                'traits' => collect(PersonRecord::traitKeys())
                    ->map(fn (string $key): array => ['value' => $key, 'label' => PersonRecord::traitLabel($key)])
                    ->all(),
            ],
        ]);
    }

    /**
     * Recibe una solicitud. Queda pendiente hasta que alguien la revise; la
     * fotografía se guarda en disco privado hasta entonces.
     */
    public function store(StorePersonRequestRequest $request, AdminNotifier $notifier): RedirectResponse
    {
        $data = $request->safe()->only([
            'type', 'name', 'sex', 'age', 'state', 'municipality', 'event_date', 'description',
            'clothing', 'distinguishing_marks', 'institution', 'contact_email', 'contact_phone',
        ]);

        $personRequest = PersonRequest::create([
            ...$data,
            'place' => trim($data['municipality'].', '.MexicanState::from($data['state'])->label()),
            'traits' => $request->traits() ?: null,
            'user_id' => $request->user()?->id,
            'status' => PersonRequestStatus::Pending,
            'photo_path' => $request->file('photo')?->store('person-requests'),
        ]);

        $notifier->send(new NoticeMail(
            noticeSubject: 'Nueva solicitud pendiente · '.$personRequest->reference(),
            kicker: 'SOLICITUD '.$personRequest->reference(),
            headline: 'Hay algo|por revisar.',
            paragraphs: [
                $personRequest->type->label().': '.($personRequest->name ?: 'sin nombre').' ('.$personRequest->placeLabel().').',
                'Está pendiente de revisión: apruébala para publicarla en el catálogo o recházala.',
            ],
            buttonLabel: 'Revisar solicitud',
            buttonUrl: route('admin.requests'),
        ));

        return redirect()->route('requests')->with('status', 'request-received');
    }

    /**
     * Ficha de una solicitud. Las pendientes o rechazadas solo las ve quien las
     * creó o quien administra.
     */
    public function show(Request $request, PersonRequest $personRequest): Response
    {
        $user = $request->user();

        abort_unless(
            $personRequest->status === PersonRequestStatus::Approved
                || ($user && ($user->is_admin || $personRequest->user_id === $user->id)),
            404,
        );

        return Inertia::render('Public/SolicitudFicha', [
            'request' => PersonRequestResource::make($personRequest),
            'canOffer' => $personRequest->status === PersonRequestStatus::Approved && ! $personRequest->isClosed(),
        ]);
    }

    /**
     * Quien tiene información escribe desde la ficha; el mensaje llega al
     * contacto de la solicitud sin mostrarle su correo a nadie más.
     */
    public function offerInformation(OfferInformationRequest $request, PersonRequest $personRequest, AdminNotifier $notifier): RedirectResponse
    {
        abort_unless($personRequest->status === PersonRequestStatus::Approved && ! $personRequest->isClosed(), 404);

        $sender = $request->user();
        $message = $request->validated('message');
        $phone = $request->validated('phone');

        InformationReport::create([
            'user_id' => $sender->id,
            'person_request_id' => $personRequest->id,
            'message' => $message,
            'phone' => $phone,
        ]);

        $notifier->attempt(fn () => Mail::to($personRequest->contact_email)
            ->send(new InformationOfferedMail($personRequest, $sender, $message, $phone)));

        // Copia para quien administra, con el contacto de ambas partes.
        $notifier->send(new NoticeMail(
            noticeSubject: 'Información recibida · '.$personRequest->reference(),
            kicker: 'INFORMACIÓN · '.$personRequest->reference(),
            headline: 'Alguien tiene|información.',
            paragraphs: [
                $sender->name.' ('.$sender->email.($phone ? ', '.$phone : '').') escribió sobre la solicitud '.$personRequest->reference().' de '.($personRequest->name ?: 'una persona').':',
                $message,
                'Ya se envió al contacto de la solicitud ('.$personRequest->contact_email.').',
            ],
            buttonLabel: 'Ver información recibida',
            buttonUrl: route('admin.information'),
        ));

        return back()->with('status', 'information-sent');
    }
}
