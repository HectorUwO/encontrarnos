<?php

namespace App\Http\Controllers;

use App\Enums\MexicanState;
use App\Http\Requests\ShowStatisticsRequest;
use App\Services\Statistics\StatisticsReport;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StatisticsController extends Controller
{
    /**
     * Estadísticas de todo el país: mapa por entidad, histórico, perfil de las
     * personas y municipios, con un periodo opcional. Cada estado tiene su
     * propia página (StateStatisticsController).
     */
    public function __invoke(ShowStatisticsRequest $request): Response|RedirectResponse
    {
        // Los enlaces de antes con ?state= llevan ahora a la página del estado.
        if ($state = $request->state()) {
            return redirect()->route('statistics.state', array_filter([
                'state' => $state->value,
                'from' => $request->fromYear(),
                'to' => $request->toYear(),
            ], fn (int|string|null $value): bool => $value !== null), 301);
        }

        $report = new StatisticsReport(null, $request->fromYear(), $request->toYear());

        return Inertia::render('Public/Estadisticas', [
            'statistics' => $report->cached(),
            'states' => MexicanState::options(),
        ]);
    }
}
