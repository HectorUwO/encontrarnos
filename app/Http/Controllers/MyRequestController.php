<?php

namespace App\Http\Controllers;

use App\Enums\MexicanState;
use App\Enums\PersonRequestStatus;
use App\Enums\PersonRequestType;
use App\Enums\Sex;
use App\Http\Requests\UpdatePersonRequestRequest;
use App\Http\Resources\PersonRequestResource;
use App\Mail\NoticeMail;
use App\Models\InformationReport;
use App\Models\PersonRecord;
use App\Models\PersonRequest;
use App\Services\AdminNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Seguimiento de las solicitudes propias: editar, dar de baja, reabrir,
 * eliminar y atender la información que llega.
 */
class MyRequestController extends Controller
{
    public function edit(Request $request, PersonRequest $personRequest): Response
    {
        $this->authorizeOwner($request, $personRequest);

        return Inertia::render('Public/SolicitudCrear', [
            'initialType' => $personRequest->type->value,
            'editing' => PersonRequestResource::make($personRequest)->resolve($request) + [
                'contact_email' => $personRequest->contact_email,
                'contact_phone' => $personRequest->contact_phone,
                'raw_traits' => $personRequest->traits ?? [],
                'event_date' => $personRequest->event_date?->toDateString(),
                'has_stored_photo' => $personRequest->hasPhoto(),
            ],
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
     * Guarda los cambios. Una solicitud ya revisada vuelve a revisión para que
     * lo publicado siga siendo lo que se aprobó.
     */
    public function update(UpdatePersonRequestRequest $request, PersonRequest $personRequest, AdminNotifier $notifier): RedirectResponse
    {
        $data = $request->safe()->only([
            'type', 'name', 'sex', 'age', 'state', 'municipality', 'event_date', 'description',
            'clothing', 'distinguishing_marks', 'institution', 'contact_email', 'contact_phone',
        ]);

        $photoPath = $personRequest->photo_path;

        if ($request->file('photo')) {
            $this->deletePhoto($personRequest);
            $photoPath = $request->file('photo')->store('person-requests');
        } elseif ($request->boolean('remove_photo')) {
            $this->deletePhoto($personRequest);
            $photoPath = null;
        }

        $wasReviewed = $personRequest->status !== PersonRequestStatus::Pending;

        $personRequest->fill([
            ...$data,
            'place' => trim($data['municipality'].', '.MexicanState::from($data['state'])->label()),
            'traits' => $request->traits() ?: null,
            'photo_path' => $photoPath,
            'status' => PersonRequestStatus::Pending,
        ])->save();

        if ($wasReviewed) {
            $notifier->send(new NoticeMail(
                noticeSubject: 'Solicitud editada · '.$personRequest->reference(),
                kicker: 'SOLICITUD '.$personRequest->reference(),
                headline: 'Una solicitud|cambió.',
                paragraphs: [
                    'Quien la publicó la editó y volvió a quedar pendiente de revisión: '.($personRequest->name ?: 'sin nombre').' ('.$personRequest->placeLabel().').',
                ],
                buttonLabel: 'Revisar solicitud',
                buttonUrl: route('admin.requests'),
            ));
        }

        return redirect()->route('dashboard')->with('status', $wasReviewed ? 'request-updated-review' : 'request-updated');
    }

    /**
     * Da de baja la solicitud (ya no aparece en el catálogo) o la marca como
     * resuelta. Se puede reabrir después.
     */
    public function close(Request $request, PersonRequest $personRequest): RedirectResponse
    {
        $this->authorizeOwner($request, $personRequest);

        $validated = $request->validate(['reason' => ['required', Rule::in(['resolved', 'withdrawn'])]]);

        $personRequest->forceFill(['closed_at' => now(), 'closed_reason' => $validated['reason']])->save();

        return back()->with('status', 'request-'.$validated['reason']);
    }

    public function reopen(Request $request, PersonRequest $personRequest): RedirectResponse
    {
        $this->authorizeOwner($request, $personRequest);

        $personRequest->forceFill(['closed_at' => null, 'closed_reason' => null])->save();

        return back()->with('status', 'request-reopened');
    }

    /**
     * Elimina la solicitud y su fotografía de forma definitiva.
     */
    public function destroy(Request $request, PersonRequest $personRequest): RedirectResponse
    {
        $this->authorizeOwner($request, $personRequest);

        $this->deletePhoto($personRequest);
        $personRequest->delete();

        return redirect()->route('dashboard')->with('status', 'request-deleted');
    }

    /**
     * Marca como atendida (o de nuevo pendiente) la información recibida.
     */
    public function attend(Request $request, InformationReport $informationReport): RedirectResponse
    {
        $owned = $informationReport->person_request_id !== null
            && PersonRequest::query()->ownedBy($request->user())->whereKey($informationReport->person_request_id)->exists();

        abort_unless($owned, 404);

        $informationReport->forceFill([
            'attended_at' => $informationReport->attended_at ? null : now(),
        ])->save();

        return back()->with('status', $informationReport->attended_at ? 'information-attended' : 'information-reopened');
    }

    private function authorizeOwner(Request $request, PersonRequest $personRequest): void
    {
        abort_unless(
            PersonRequest::query()->ownedBy($request->user())->whereKey($personRequest->id)->exists(),
            404,
        );
    }

    private function deletePhoto(PersonRequest $personRequest): void
    {
        if ($personRequest->hasPhoto()) {
            Storage::disk(config('filesystems.default'))->delete($personRequest->photo_path);
        }
    }
}
