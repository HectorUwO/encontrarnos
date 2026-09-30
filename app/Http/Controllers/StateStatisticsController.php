<?php

namespace App\Http\Controllers;

use App\Enums\MexicanState;
use App\Http\Requests\ShowStateStatisticsRequest;
use App\Services\Statistics\StatisticsReport;
use Inertia\Inertia;
use Inertia\Response;

class StateStatisticsController extends Controller
{
    /**
     * Página de un estado: sus cifras, su histórico, sus municipios y cómo se
     * compara con el resto del país, con un periodo opcional.
     */
    public function __invoke(ShowStateStatisticsRequest $request, MexicanState $state): Response
    {
        $report = new StatisticsReport($state, $request->fromYear(), $request->toYear());

        return Inertia::render('Public/EstadisticasEstado', [
            'statistics' => $report->cached(),
            'states' => MexicanState::options(),
        ]);
    }
}
