<?php

namespace App\Http\Controllers;

use App\Enums\PersonRequestStatus;
use App\Http\Requests\StorePersonRequestRequest;
use App\Http\Resources\PersonRequestResource;
use App\Models\PersonRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PersonRequestController extends Controller
{
    private const BOARD_SIZE = 12;

    /**
     * Tablón público: solo solicitudes ya revisadas y aprobadas.
     */
    public function index(): Response
    {
        $requests = PersonRequest::query()
            ->approved()
            ->latest()
            ->latest('id')
            ->limit(self::BOARD_SIZE)
            ->get();

        return Inertia::render('Public/Solicitudes', [
            'requests' => PersonRequestResource::collection($requests),
        ]);
    }

    /**
     * Recibe una solicitud. Queda pendiente hasta que alguien la revise; la
     * fotografía se guarda en disco privado hasta entonces.
     */
    public function store(StorePersonRequestRequest $request): RedirectResponse
    {
        PersonRequest::create([
            ...$request->safe()->only(['type', 'name', 'place', 'description', 'contact_email']),
            'user_id' => $request->user()?->id,
            'status' => PersonRequestStatus::Pending,
            'photo_path' => $request->file('photo')?->store('person-requests'),
        ]);

        return back();
    }
}
