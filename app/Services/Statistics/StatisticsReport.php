<?php

namespace App\Services\Statistics;

use App\Enums\AgeRange;
use App\Enums\DisappearanceStatus;
use App\Enums\MexicanState;
use App\Enums\Sex;
use App\Models\RegistryCount;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Arma los datos de la página de estadísticas a partir de los conteos del
 * registro nacional (RegistryCount).
 *
 * Cerca de la tercera parte de los registros es confidencial, y la proporción
 * cambia mucho de un estado a otro: de ellos solo se conoce el estado y el
 * municipio. Por eso los totales por estado y municipio cuentan todos los
 * registros, pero el histórico, el sexo, la edad y el estatus solo cuentan los
 * que informan esos datos, y filtrar por periodo deja fuera a los
 * confidenciales. Como el peso de los confidenciales no es parejo, no se
 * calculan tendencias ni comparaciones entre periodos.
 */
class StatisticsReport
{
    private const TOP_MUNICIPALITIES = 10;

    private const NATIONAL_POPULATION = 126_014_024;

    private const VERSION_KEY = 'statistics.version';

    /**
     * Súbelo cuando cambie la forma del informe: así los informes guardados con
     * la forma anterior dejan de usarse sin esperar a la siguiente importación.
     */
    private const SCHEMA = 2;

    public function __construct(
        private readonly ?MexicanState $state = null,
        private readonly ?int $from = null,
        private readonly ?int $to = null,
    ) {}

    /**
     * El informe solo cambia cuando se importa el listado, así que se guarda
     * hasta la siguiente importación; sin datos no se guarda para que aparezca
     * en cuanto lleguen.
     *
     * Solo se guardan las vistas de todo el país y de cada estado (33 en
     * total). Cada combinación de periodo sería otra copia de unos 40 KB que
     * cualquiera podría multiplicar pidiendo años distintos.
     *
     * @return array<string, mixed>
     */
    public function cached(): array
    {
        if ($this->from !== null || $this->to !== null) {
            return $this->build();
        }

        $key = implode(':', [
            'statistics',
            self::SCHEMA,
            Cache::get(self::VERSION_KEY, '0'),
            $this->state?->value ?? 'all',
        ]);

        $report = Cache::get($key);

        if ($report === null) {
            $report = $this->build();

            if ($report['has_data']) {
                Cache::put($key, $report, now()->addDay());
            }
        }

        return $report;
    }

    /**
     * Descarta los informes guardados; se llama cuando cambian los conteos.
     */
    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, (string) Str::uuid());
    }

    /**
     * Calcula y guarda el informe de todo el país y el de cada estado.
     *
     * @param  callable(): void|null  $advance  Se llama después de cada informe.
     */
    public static function warm(?callable $advance = null): void
    {
        foreach ([null, ...MexicanState::cases()] as $state) {
            (new self($state))->cached();

            if ($advance !== null) {
                $advance();
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        if (! RegistryCount::query()->exists()) {
            return ['has_data' => false];
        }

        ['totals' => $totals, 'meta' => $meta, 'registry_by_state' => $registryByState] = $this->globals();

        $totalsByState = $this->totalsByState();
        $entities = $this->entities($totalsByState, $registryByState);
        $unknown = $totalsByState[''] ?? 0;
        $timeline = $this->timeline();
        $summary = $this->summary($entities, $unknown, $timeline, $totals, $registryByState);

        return [
            'has_data' => true,
            'filters' => [
                'state' => $this->state?->value,
                'from' => $this->from,
                'to' => $this->to,
            ],
            'meta' => $meta,
            'totals' => $totals,
            'summary' => $summary,
            'entities' => $entities,
            'unknown_state' => $unknown,
            'timeline' => $timeline,
            'profile' => $this->profile(),
            'municipalities' => $this->municipalities($summary['total']),
            'unknown_municipality' => $this->unknownMunicipality(),
        ];
    }

    /**
     * Cifras de todo el registro que no dependen de los filtros. Cada una
     * recorre toda la tabla, así que se guardan aparte hasta la siguiente
     * importación: pedir un periodo o un estado ya no las recalcula.
     *
     * @return array{totals: array<string, int>, meta: array<string, mixed>, registry_by_state: array<string, array{registry: int, confidential: int}>}
     */
    private function globals(): array
    {
        return Cache::remember(
            implode(':', ['statistics', self::SCHEMA, Cache::get(self::VERSION_KEY, '0'), 'globals']),
            now()->addDay(),
            fn (): array => [
                'totals' => $this->nationalTotals(),
                'meta' => $this->meta(),
                'registry_by_state' => $this->registryByState(),
            ],
        );
    }

    /**
     * @return array{first_year: int|null, last_year: int|null, latest_month: string|null, imported_at: string|null}
     */
    private function meta(): array
    {
        $first = RegistryCount::query()->min('month');
        $latest = RegistryCount::query()->max('month');
        $importedAt = RegistryCount::query()->max('updated_at');

        return [
            'first_year' => $first === null ? null : (int) substr($first, 0, 4),
            'last_year' => $latest === null ? null : (int) substr($latest, 0, 4),
            'latest_month' => $latest === null ? null : substr($latest, 0, 7),
            'imported_at' => $importedAt === null ? null : Carbon::parse($importedAt)->toDateString(),
        ];
    }

    /**
     * Totales de todo el registro, sin filtros.
     *
     * @return array{registry: int, confidential: int, dated: int, undated: int}
     */
    private function nationalTotals(): array
    {
        $row = RegistryCount::query()->toBase()
            ->selectRaw('sum(total) as registry')
            ->selectRaw('sum(case when confidential = ? then total else 0 end) as confidential', [true])
            ->selectRaw('sum(case when month is not null then total else 0 end) as dated')
            ->first();

        return [
            'registry' => (int) $row->registry,
            'confidential' => (int) $row->confidential,
            'dated' => (int) $row->dated,
            'undated' => (int) $row->registry - (int) $row->confidential - (int) $row->dated,
        ];
    }

    /**
     * Total por estado con el filtro de periodo aplicado; la clave vacía es el
     * grupo «se desconoce».
     *
     * @return array<string, int>
     */
    private function totalsByState(): array
    {
        $rows = RegistryCount::query()
            ->withinYears($this->from, $this->to)
            ->toBase()
            ->selectRaw('state, sum(total) as total')
            ->groupBy('state')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[(string) $row->state] = (int) $row->total;
        }

        return $totals;
    }

    /**
     * Registros de cada estado en todo el tiempo, y cuántos son confidenciales;
     * la clave vacía es el grupo «se desconoce».
     *
     * @return array<string, array{registry: int, confidential: int}>
     */
    private function registryByState(): array
    {
        $rows = RegistryCount::query()->toBase()
            ->selectRaw('state, sum(total) as registry')
            ->selectRaw('sum(case when confidential = ? then total else 0 end) as confidential', [true])
            ->groupBy('state')
            ->get();

        $byState = [];

        foreach ($rows as $row) {
            $byState[(string) $row->state] = ['registry' => (int) $row->registry, 'confidential' => (int) $row->confidential];
        }

        return $byState;
    }

    /**
     * @param  array<string, int>  $totalsByState
     * @param  array<string, array{registry: int, confidential: int}>  $allTime
     * @return list<array<string, mixed>>
     */
    private function entities(array $totalsByState, array $allTime): array
    {
        $entities = collect(MexicanState::cases())
            ->map(function (MexicanState $state) use ($totalsByState, $allTime): array {
                $total = $totalsByState[$state->value] ?? 0;
                $registry = $allTime[$state->value]['registry'] ?? 0;
                $confidential = $allTime[$state->value]['confidential'] ?? 0;

                return [
                    'state' => $state->value,
                    'label' => $state->label(),
                    'code' => $state->code(),
                    'total' => $total,
                    'per_100k' => round($total / $state->population() * 100_000, 1),
                    'registry_total' => $registry,
                    'confidential' => $confidential,
                    'confidential_share' => $registry > 0 ? round($confidential / $registry * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->map(fn (array $entity, int $index): array => [...$entity, 'rank' => $index + 1]);

        // Lugar por registros y lugar por registros cada 100 mil habitantes.
        $rateRank = $entities->sortByDesc('per_100k')->pluck('state')->values()->flip();

        return $entities
            ->map(fn (array $entity): array => [...$entity, 'rate_rank' => $rateRank[$entity['state']] + 1])
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $entities
     * @param  list<array{month: string, total: int}>  $timeline
     * @param  array{registry: int, confidential: int, dated: int, undated: int}  $totals
     * @param  array<string, array{registry: int, confidential: int}>  $registryByState
     * @return array<string, mixed>
     */
    private function summary(array $entities, int $unknown, array $timeline, array $totals, array $registryByState): array
    {
        $nationalTotal = array_sum(array_column($entities, 'total')) + $unknown;
        $entity = $this->state === null
            ? null
            : collect($entities)->firstWhere('state', $this->state->value);

        $total = $entity['total'] ?? $nationalTotal;
        $population = $this->state?->population() ?? self::NATIONAL_POPULATION;
        $registry = $this->state === null ? $totals['registry'] : ($registryByState[$this->state->value]['registry'] ?? 0);
        $confidential = $this->state === null ? $totals['confidential'] : ($registryByState[$this->state->value]['confidential'] ?? 0);

        return [
            'scope' => $this->state?->label() ?? 'México',
            'total' => $total,
            'share' => $nationalTotal > 0 ? round($total / $nationalTotal * 100, 1) : 0.0,
            'rank' => $entity['rank'] ?? null,
            'rate_rank' => $entity['rate_rank'] ?? null,
            'population' => $population,
            'per_100k' => round($total / $population * 100_000, 1),
            'registry_total' => $registry,
            'confidential' => $confidential,
            'confidential_share' => $registry > 0 ? round($confidential / $registry * 100, 1) : 0.0,
            'peak_year' => $this->peakYear($timeline),
            // Para comparar un estado con el país en las mismas condiciones.
            'national' => [
                'total' => $nationalTotal,
                'population' => self::NATIONAL_POPULATION,
                'per_100k' => round($nationalTotal / self::NATIONAL_POPULATION * 100_000, 1),
                'confidential_share' => $totals['registry'] > 0 ? round($totals['confidential'] / $totals['registry'] * 100, 1) : 0.0,
            ],
        ];
    }

    /**
     * Histórico mensual del alcance elegido (todo el país o un estado), sin
     * el filtro de periodo para que siempre se vea la historia completa.
     *
     * @return list<array{month: string, total: int}>
     */
    private function timeline(): array
    {
        return RegistryCount::query()
            ->inState($this->state)
            ->whereNotNull('month')
            ->toBase()
            ->selectRaw('month, sum(total) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn (object $row): array => [
                'month' => substr($row->month, 0, 7),
                'total' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @param  list<array{month: string, total: int}>  $timeline
     * @return array{year: int, total: int}|null
     */
    private function peakYear(array $timeline): ?array
    {
        $years = [];

        foreach ($timeline as $point) {
            $year = (int) substr($point['month'], 0, 4);
            $years[$year] = ($years[$year] ?? 0) + $point['total'];
        }

        if ($years === []) {
            return null;
        }

        arsort($years);

        return ['year' => array_key_first($years), 'total' => reset($years)];
    }

    /**
     * Sexo, edad y estatus de los registros que los informan.
     *
     * @return array<string, mixed>
     */
    private function profile(): array
    {
        $rows = RegistryCount::query()
            ->inState($this->state)
            ->withinYears($this->from, $this->to)
            ->where('confidential', false)
            ->toBase()
            ->selectRaw('sex, age_range, status, sum(total) as total')
            ->groupBy('sex', 'age_range', 'status')
            ->get();

        $bySex = [];
        $byAge = [];
        $byStatus = [];

        foreach ($rows as $row) {
            $bySex[(string) $row->sex] = ($bySex[(string) $row->sex] ?? 0) + (int) $row->total;
            $byAge[(string) $row->age_range] = ($byAge[(string) $row->age_range] ?? 0) + (int) $row->total;
            $byStatus[(string) $row->status] = ($byStatus[(string) $row->status] ?? 0) + (int) $row->total;
        }

        return [
            'known' => array_sum($bySex),
            'sex' => array_map(
                fn (Sex $sex): array => ['value' => $sex->value, 'label' => $sex->label(), 'count' => $bySex[$sex->value] ?? 0],
                Sex::cases(),
            ),
            'age' => [
                ...array_map(
                    fn (AgeRange $range): array => ['value' => $range->value, 'label' => $range->label(), 'count' => $byAge[$range->value] ?? 0],
                    AgeRange::cases(),
                ),
                ['value' => null, 'label' => 'Sin dato de edad', 'count' => $byAge[''] ?? 0],
            ],
            'status' => array_map(
                fn (DisappearanceStatus $status): array => ['value' => $status->value, 'label' => $status->label(), 'count' => $byStatus[$status->value] ?? 0],
                DisappearanceStatus::cases(),
            ),
        ];
    }

    /**
     * Municipios con más registros en el alcance y periodo elegidos: los 10
     * primeros de todo el país o, al elegir un estado, todos los suyos. El
     * porcentaje es sobre todos los registros del alcance, incluidos los que no
     * indican municipio.
     *
     * @return list<array{name: string, state: string|null, state_label: string|null, total: int, share: float, confidential: int, confidential_share: float}>
     */
    private function municipalities(int $scopeTotal): array
    {
        return RegistryCount::query()
            ->inState($this->state)
            ->withinYears($this->from, $this->to)
            ->whereNotNull('municipality')
            ->toBase()
            ->selectRaw('state, municipality, sum(total) as total')
            ->selectRaw('sum(case when confidential = ? then total else 0 end) as confidential', [true])
            ->groupBy('state', 'municipality')
            ->orderByDesc('total')
            ->orderBy('municipality')
            ->when($this->state === null, fn (Builder $query) => $query->limit(self::TOP_MUNICIPALITIES))
            ->get()
            ->map(fn (object $row): array => [
                'name' => $this->municipalityName($row->municipality),
                'state' => $row->state,
                'state_label' => $row->state === null ? null : MexicanState::from($row->state)->label(),
                'total' => (int) $row->total,
                'share' => $scopeTotal > 0 ? round($row->total / $scopeTotal * 100, 1) : 0.0,
                'confidential' => (int) $row->confidential,
                'confidential_share' => $row->total > 0 ? round($row->confidential / $row->total * 100, 1) : 0.0,
            ])
            ->all();
    }

    /**
     * Registros del alcance y periodo elegidos que no indican municipio.
     */
    private function unknownMunicipality(): int
    {
        return (int) RegistryCount::query()
            ->inState($this->state)
            ->withinYears($this->from, $this->to)
            ->whereNull('municipality')
            ->sum('total');
    }

    private function municipalityName(string $name): string
    {
        $title = Str::title(mb_strtolower($name));

        return preg_replace_callback(
            '/ (De|Del|La|Las|Los|El|Y|En|A)\b/u',
            fn (array $match): string => ' '.mb_strtolower($match[1]),
            $title,
        ) ?? $title;
    }
}
