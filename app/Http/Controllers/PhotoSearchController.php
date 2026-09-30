<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePhotoSearchRequest;
use App\Http\Resources\PersonRecordResource;
use App\Services\PhotoSearch\PhotoMatcher;
use Inertia\Inertia;
use Inertia\Response;

class PhotoSearchController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Public/BusquedaFotografia');
    }

    /**
     * Compara la fotografía con las fichas publicadas. La imagen no se guarda:
     * se procesa en la petición y se descarta.
     */
    public function store(StorePhotoSearchRequest $request, PhotoMatcher $matcher): Response
    {
        $available = $matcher->isAvailable();

        return Inertia::render('Public/BusquedaFotografia', [
            'search' => [
                'available' => $available,
                'matches' => PersonRecordResource::collection(
                    $available ? $matcher->match($request->file('photo')) : collect(),
                )->resolve(),
            ],
        ]);
    }
}
