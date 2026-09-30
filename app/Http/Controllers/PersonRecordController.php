<?php

namespace App\Http\Controllers;

use App\Enums\AgeRange;
use App\Enums\MexicanState;
use App\Enums\RecordType;
use App\Http\Requests\IndexPersonRecordsRequest;
use App\Http\Resources\PersonRecordResource;
use App\Models\PersonRecord;
use App\Services\Search\RecordSearch;
use Inertia\Inertia;
use Inertia\Response;

class PersonRecordController extends Controller
{
    private const PER_PAGE = 12;

    /**
     * Base de datos pública de fichas, con búsqueda y filtros.
     */
    public function index(IndexPersonRecordsRequest $request, RecordSearch $search): Response
    {
        $records = $search
            ->paginate(
                $request->searchTerm(),
                $request->state(),
                $request->ageRange(),
                $request->recordType(),
                self::PER_PAGE,
            )
            ->withQueryString();

        return Inertia::render('Public/BaseDeDatos', [
            'records' => PersonRecordResource::collection($records),
            'totalPublished' => PersonRecord::publishedCount(),
            'filters' => $request->filters(),
            'options' => [
                'states' => MexicanState::options(),
                'ageRanges' => AgeRange::options(),
                'types' => RecordType::options(),
            ],
        ]);
    }
}
