<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePhotoSearchRequest;
use App\Http\Resources\PersonRecordResource;
use App\Http\Resources\PersonRequestResource;
use App\Models\PersonRecord;
use App\Models\PersonRequest;
use App\Services\PhotoSearch\PhotoMatcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
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
        $matches = collect();
        try {
            $matches = $available ? $matcher->match($request->file('photo')) : collect();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('CompreFace no está disponible.', ['exception_type' => $exception::class]);
            $available = false;
        }

        $results = $matches->map(function (PersonRecord|PersonRequest $person): array {
            $data = $person instanceof PersonRecord
                ? (new PersonRecordResource($person))->resolve()
                : (new PersonRequestResource($person))->resolve();

            return array_merge($data, [
                'source' => $person instanceof PersonRecord ? 'record' : 'request',
                'similarity' => $person->getAttribute('face_similarity'),
                'portrait' => $data['portrait'] ?? $data['photo_thumb'] ?? null,
            ]);
        });
        $primaryMatches = $results
            ->filter(fn (array $person): bool => $person['similarity'] > config('services.compreface.primary_threshold'))
            ->take(config('services.compreface.primary_limit'));

        return Inertia::render('Public/BusquedaFotografia', [
            'search' => [
                'available' => $available,
                'matches' => $primaryMatches->values()->all(),
                'other_matches' => $results->except($primaryMatches->keys()->all())->values()->all(),
            ],
        ]);
    }
}
