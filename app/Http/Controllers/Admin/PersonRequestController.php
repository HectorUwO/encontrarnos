<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PersonRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PersonRequestResource;
use App\Mail\NoticeMail;
use App\Models\PersonRequest;
use App\Services\AdminNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PersonRequestController extends Controller
{
    /**
     * Revisión de solicitudes: quien administra ve también el contacto, que
     * nunca se publica.
     */
    public function index(Request $request): Response
    {
        $status = $request->enum('estado', PersonRequestStatus::class) ?? PersonRequestStatus::Pending;

        $requests = PersonRequest::query()
            ->where('status', $status)
            ->oldest()
            ->oldest('id')
            ->with('user:id,email')
            ->paginate(10)
            ->withQueryString();

        // Los contactos se leen antes de convertir las filas en recursos públicos.
        $contacts = $requests->getCollection()->mapWithKeys(fn (PersonRequest $item): array => [
            $item->id => [
                'email' => $item->contact_email,
                'phone' => $item->contact_phone,
                'author' => $item->user?->email,
                'photo' => $item->hasPhoto()
                    ? route('admin.requests.photo', $item, absolute: false)
                    : null,
            ],
        ]);

        return Inertia::render('Admin/Requests', [
            'requests' => PersonRequestResource::collection($requests)->additional(['contacts' => $contacts]),
            'status' => $status->value,
            'counts' => collect(PersonRequestStatus::cases())->mapWithKeys(fn (PersonRequestStatus $case): array => [
                $case->value => PersonRequest::where('status', $case)->count(),
            ]),
        ]);
    }

    /**
     * Fotografía original, aunque la solicitud aún no esté publicada.
     */
    public function photo(PersonRequest $personRequest): StreamedResponse
    {
        $disk = Storage::disk(config('filesystems.default'));

        abort_unless($personRequest->hasPhoto() && $disk->exists($personRequest->photo_path), 404);

        return $disk->response($personRequest->photo_path, null, ['Cache-Control' => 'private, max-age=300']);
    }

    public function update(Request $request, PersonRequest $personRequest, AdminNotifier $notifier): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(PersonRequestStatus::class)],
        ]);

        $previous = $personRequest->status;
        $personRequest->update(['status' => $validated['status']]);

        if ($previous !== $personRequest->status) {
            $this->notifyRequester($personRequest, $notifier);
        }

        return back()->with('status', 'request-'.$validated['status']);
    }

    /**
     * Avisa a quien envió la solicitud cuando se publica o se rechaza.
     */
    private function notifyRequester(PersonRequest $personRequest, AdminNotifier $notifier): void
    {
        $notice = match ($personRequest->status) {
            PersonRequestStatus::Approved => new NoticeMail(
                noticeSubject: 'Tu solicitud '.$personRequest->reference().' ya está publicada',
                kicker: 'SOLICITUD '.$personRequest->reference(),
                headline: 'Tu solicitud|ya es pública.',
                paragraphs: [
                    'Revisamos tu solicitud y ya aparece en el catálogo. Si alguien tiene información, te escribirá a este correo.',
                ],
                buttonLabel: 'Ver la ficha',
                buttonUrl: route('requests.show', $personRequest),
            ),
            PersonRequestStatus::Rejected => new NoticeMail(
                noticeSubject: 'Sobre tu solicitud '.$personRequest->reference(),
                kicker: 'SOLICITUD '.$personRequest->reference(),
                headline: 'No pudimos|publicarla.',
                paragraphs: [
                    'Revisamos tu solicitud y por ahora no la publicamos. Puedes enviar una nueva con más datos, por ejemplo una descripción más precisa o una fotografía.',
                ],
                buttonLabel: 'Crear una solicitud',
                buttonUrl: route('requests.create'),
            ),
            default => null,
        };

        if ($notice) {
            $notifier->attempt(fn () => Mail::to($personRequest->contact_email)->send($notice));
        }
    }
}
