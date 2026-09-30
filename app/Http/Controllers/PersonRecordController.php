<?php

namespace App\Http\Controllers;

use App\Enums\AgeRange;
use App\Enums\DisappearanceStatus;
use App\Enums\MexicanState;
use App\Enums\Sex;
use App\Http\Requests\IndexPersonRecordsRequest;
use App\Http\Resources\PersonRecordDetailResource;
use App\Http\Resources\PersonRecordResource;
use App\Models\PersonRecord;
use App\Services\Search\RecordSearch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PersonRecordController extends Controller
{
    private const PER_PAGE = 12;

    /**
     * Ficha de una persona desaparecida. Solo las fichas públicas se muestran.
     */
    public function show(PersonRecord $personRecord): Response
    {
        abort_unless($personRecord->published_at !== null, 404);

        return Inertia::render('Public/FichaDesaparecido', [
            'record' => PersonRecordDetailResource::make($personRecord),
        ]);
    }

    /**
     * Base de datos pública de fichas, con búsqueda y filtros.
     */
    public function index(IndexPersonRecordsRequest $request, RecordSearch $search): Response
    {
        $records = $search
            ->search($request->recordQuery(), self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Public/BaseDeDatos', [
            'records' => PersonRecordResource::collection($records),
            'totalPublished' => PersonRecord::publishedCount(),
            'filters' => $request->filters(),
            'options' => [
                'states' => MexicanState::options(),
                'ageRanges' => AgeRange::options(),
                'sexes' => Sex::options(),
                'statuses' => DisappearanceStatus::options(),
                'sorts' => $this->sortOptions(),
                'nationalities' => $this->nationalities(),
                'registry' => Gate::allows('view-registry-publication')
                    ? [
                        ['value' => 'SI', 'label' => 'Autorizadas'],
                        ['value' => 'NO', 'label' => 'No autorizadas'],
                        ['value' => 'SIN DATO', 'label' => 'Sin dato'],
                    ]
                    : null,
            ],
        ]);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function sortOptions(): array
    {
        return [
            ['value' => 'recent', 'label' => 'Desaparición más reciente'],
            ['value' => 'oldest', 'label' => 'Desaparición más antigua'],
            ['value' => 'name', 'label' => 'Nombre (A–Z)'],
            ['value' => 'age_asc', 'label' => 'Edad (menor a mayor)'],
            ['value' => 'age_desc', 'label' => 'Edad (mayor a menor)'],
            ['value' => 'added', 'label' => 'Agregadas recientemente'],
        ];
    }

    /**
     * Nacionalidades más frecuentes, para el filtro. Cambian solo al importar.
     *
     * @return list<array{value: string, label: string}>
     */
    private function nationalities(): array
    {
        return Cache::remember('records.nationalities', now()->addHours(6), fn (): array => PersonRecord::query()
            ->published()
            ->whereNotNull('nationality')
            ->groupBy('nationality')
            ->orderByRaw('count(*) desc')
            ->limit(30)
            ->pluck('nationality')
            ->map(fn (string $value): array => ['value' => $value, 'label' => Str::title(Str::lower($value))])
            ->all());
    }
}
