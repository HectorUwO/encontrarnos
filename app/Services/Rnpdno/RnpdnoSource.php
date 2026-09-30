<?php

namespace App\Services\Rnpdno;

use Generator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Lee, siempre en solo lectura, las fichas que reunió el colector RNPDNO.
 *
 * La base del colector pesa decenas de GB porque guarda respuestas completas
 * en JSON, así que solo se consultan por clave e índice: primero los ids de
 * las fichas completas y luego, por lotes, sus datos.
 */
class RnpdnoSource
{
    /**
     * Campos de la ficha que se leen: todo lo que trae el registro salvo las
     * imágenes incrustadas en base64 (`imagen*`, `pleca_segob`), que pesan
     * megas y ya se guardan aparte como archivo.
     *
     * @var list<string>
     */
    public const FIELD_KEYS = [
        // Identificación y descripción.
        'nombre',
        'primerapellido',
        'segundoapellido',
        'Sexo',
        'edadHechos',
        'edadanios',
        'edadmeses',
        'edaddias',
        'edadActual',
        'MediaFiliacion',
        'PrendasDeVestir',
        'SanaParticular',
        'Nacionalidad',
        'hablaespaniol',
        'TieneDiscapacidad',
        'TipoDiscapacidad',
        // Hechos y trámite.
        'estadoHecho',
        'estado',
        'municipioHecho',
        'municipio',
        'ffechahechos',
        'fechahechos',
        'ffechapercato',
        'fechapercato',
        'fechacaptura',
        'fechaAct',
        'EstatusVictima',
        'PublicarFicha',
        'SoloBusqueda',
        'Inicio',
        'archivomigracion',
        'PertenenciaDependenicaOrigen',
        'PertenenciaPorCanalizacion',
        'iddependenciaorigen',
        // Datos personales sensibles.
        'fechanacimiento',
        'estadonacimiento',
        'lugarnacimiento',
        'calle',
        'noexterior',
        'nointerior',
        'codigopostal',
        'nombreasentamiento',
    ];

    public function __construct(private readonly string $connection = 'rnpdno') {}

    /**
     * Ids de las fichas con datos completos en todas las corridas del
     * colector, en orden ascendente.
     *
     * @return list<int>
     */
    public function completeReportIds(): array
    {
        $database = $this->database();
        $ids = [];

        foreach ($database->table('runs')->orderBy('id')->pluck('id') as $runId) {
            $runIds = $database->table('reports')
                ->where('run_id', $runId)
                ->where('status', 'complete')
                ->orderBy('id')
                ->pluck('id');

            foreach ($runIds as $id) {
                $ids[] = (int) $id;
            }
        }

        sort($ids);

        return $ids;
    }

    /**
     * Datos de un lote de fichas. Si un campo aparece varias veces se queda con
     * la última aparición. La foto solo se incluye cuando el registro confirma
     * que la ficha tiene fotografía.
     *
     * @param  list<int>  $reportIds
     * @return list<array{
     *     victim_id: string,
     *     report_id: int,
     *     agency_id: int,
     *     fields: array<string, mixed>,
     *     photo: array{sha256: string, path: string}|null,
     * }>
     */
    public function load(array $reportIds): array
    {
        if ($reportIds === []) {
            return [];
        }

        $database = $this->database();

        $reports = $database->table('reports')
            ->whereIn('id', $reportIds)
            ->get(['id', 'victim_id', 'report_id', 'agency_id', 'image_status', 'image_sha256'])
            ->keyBy('id');

        $fields = [];

        $fieldRows = $database->table('fields')
            ->whereIn('report_id', $reportIds)
            ->whereIn('key', self::FIELD_KEYS)
            ->orderBy('report_id')
            ->orderBy('position')
            ->get(['report_id', 'key', 'value_json']);

        foreach ($fieldRows as $row) {
            $fields[$row->report_id][$row->key] = json_decode($row->value_json, true);
        }

        $imagePaths = $database->table('images')
            ->whereIn('sha256', $reports->pluck('image_sha256')->filter()->unique()->values()->all())
            ->pluck('path', 'sha256');

        $batch = [];

        foreach ($reportIds as $id) {
            $report = $reports->get($id);

            if ($report === null) {
                continue;
            }

            $sha256 = $report->image_status === 'saved' ? $report->image_sha256 : null;

            $batch[] = [
                'victim_id' => (string) $report->victim_id,
                'report_id' => (int) $report->report_id,
                'agency_id' => (int) $report->agency_id,
                'fields' => $fields[$id] ?? [],
                'photo' => $sha256 !== null && isset($imagePaths[$sha256])
                    ? ['sha256' => $sha256, 'path' => $imagePaths[$sha256]]
                    : null,
            ];
        }

        return $batch;
    }

    /**
     * Registros del listado público que reunió la corrida completa más
     * reciente, uno por uno y de a una partición (estado y municipio) para no
     * cargarlos todos en memoria. Solo se lee la fila del listado; nunca la
     * respuesta completa que el colector guarda aparte.
     *
     * El estado y el municipio salen de la partición con la que se consultó el
     * registro: en los registros confidenciales el propio registro dice
     * «CONFIDENCIAL» en el municipio y en todo lo demás.
     *
     * @return Generator<int, array{
     *     state_code: int,
     *     municipality: string,
     *     confidential: bool,
     *     fields: array<string, mixed>,
     * }>
     */
    public function listing(): Generator
    {
        $database = $this->database();
        $runId = $database->table('runs')->where('mode', 'full')->orderByDesc('id')->value('id');

        if ($runId === null) {
            return;
        }

        $partitions = $database->table('partitions')
            ->where('run_id', $runId)
            ->orderBy('id')
            ->get(['id', 'state', 'name']);

        foreach ($partitions as $partition) {
            $rows = $database->table('observations')
                ->where('partition_id', $partition->id)
                ->get(['raw_json', 'confidential']);

            foreach ($rows as $row) {
                yield [
                    'state_code' => (int) $partition->state,
                    'municipality' => (string) $partition->name,
                    'confidential' => (bool) $row->confidential,
                    'fields' => json_decode($row->raw_json, true) ?: [],
                ];
            }
        }
    }

    private function database(): ConnectionInterface
    {
        return DB::connection($this->connection);
    }
}
