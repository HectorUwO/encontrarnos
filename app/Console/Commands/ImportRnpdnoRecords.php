<?php

namespace App\Console\Commands;

use App\Models\PersonRecord;
use App\Services\Rnpdno\RnpdnoRecordMapper;
use App\Services\Rnpdno\RnpdnoSource;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

#[Signature('records:import-rnpdno {--chunk=500 : Fichas por lote} {--limit= : Máximo de fichas a procesar} {--dry-run : Calcula el resultado sin escribir en la base} {--no-index : No actualiza el índice de búsqueda al terminar}')]
#[Description('Importa las fichas completas del colector RNPDNO (solo lectura) a person_records')]
class ImportRnpdnoRecords extends Command
{
    private int $lastFolioNumber = 0;

    /**
     * Execute the console command.
     */
    public function handle(RnpdnoSource $source, RnpdnoRecordMapper $mapper): int
    {
        $lock = Cache::lock('records:import-rnpdno', 6 * 3600);

        if (! $lock->get()) {
            $this->components->error('Ya hay una importación en curso.');

            return self::FAILURE;
        }

        try {
            return $this->import($source, $mapper);
        } finally {
            $lock->release();
        }
    }

    private function import(RnpdnoSource $source, RnpdnoRecordMapper $mapper): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(1, (int) $this->option('chunk'));

        $ids = $source->completeReportIds();

        if ($this->option('limit') !== null) {
            $ids = array_slice($ids, 0, max(0, (int) $this->option('limit')));
        }

        $this->components->info(sprintf('%s fichas completas en el colector%s.', number_format(count($ids)), $dryRun ? ' (simulación)' : ''));

        $this->lastFolioNumber = (int) substr((string) PersonRecord::query()->max('folio'), 3);

        $totals = ['created' => 0, 'updated' => 0, 'published' => 0, 'unpublished' => 0, 'with_photo' => 0, 'skipped' => 0];

        $this->withProgressBar(array_chunk($ids, $chunkSize), function (array $chunk) use ($source, $mapper, $dryRun, &$totals): void {
            $rows = [];

            foreach ($source->load($chunk) as $report) {
                if ($report['fields'] === [] || $report['victim_id'] === '') {
                    $totals['skipped']++;

                    continue;
                }

                $key = $this->sourceKey($report['victim_id'], $report['report_id'], $report['agency_id']);

                $rows[$key] = [
                    ...$mapper->map($report['fields']),
                    'photo_sha256' => $report['photo']['sha256'] ?? null,
                    'photo_path' => $report['photo']['path'] ?? null,
                    'source_victim_id' => $report['victim_id'],
                    'source_report_id' => $report['report_id'],
                    'source_agency_id' => $report['agency_id'],
                    'publishable' => $mapper->isPublishable($report['fields']),
                ];
            }

            if (! $dryRun) {
                $this->store($rows, $totals);
            } else {
                $this->tally($rows, [], $totals);
            }
        });

        if (! $dryRun) {
            Cache::forget(PersonRecord::PUBLISHED_COUNT_KEY);
        }

        $this->newLine(2);
        $this->components->twoColumnDetail('Fichas nuevas', number_format($totals['created']));
        $this->components->twoColumnDetail('Fichas actualizadas', number_format($totals['updated']));
        $this->components->twoColumnDetail('Publicables (visibles)', number_format($totals['published']));
        $this->components->twoColumnDetail('No publicables (ocultas)', number_format($totals['unpublished']));
        $this->components->twoColumnDetail('Con fotografía', number_format($totals['with_photo']));
        $this->components->twoColumnDetail('Omitidas por falta de datos', number_format($totals['skipped']));

        // La búsqueda con Meilisearch solo ve lo que está en su índice.
        if (! $dryRun && ! $this->option('no-index') && config('services.records_search') === 'meilisearch') {
            $this->newLine();
            $this->components->info('Actualizando el índice de búsqueda…');

            if ($this->call('records:index') !== self::SUCCESS) {
                $this->components->warn('Las fichas se importaron, pero el índice no se actualizó: corre «php artisan records:index» cuando Meilisearch esté disponible.');
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @param  array<string, int>  $totals
     */
    private function store(array $rows, array &$totals): void
    {
        if ($rows === []) {
            return;
        }

        $existing = PersonRecord::query()
            ->whereIn('source_victim_id', collect($rows)->pluck('source_victim_id')->unique()->all())
            ->get(['id', 'folio', 'source_victim_id', 'source_report_id', 'source_agency_id', 'published_at'])
            ->keyBy(fn (PersonRecord $record): string => $this->sourceKey(
                $record->source_victim_id,
                $record->source_report_id,
                $record->source_agency_id,
            ));

        $this->tally($rows, $existing->all(), $totals);

        $now = now();

        $payload = collect($rows)->map(function (array $row, string $key) use ($existing, $now): array {
            $current = $existing->get($key);
            $publishable = $row['publishable'];
            unset($row['publishable']);

            return [
                ...$row,
                'folio' => $current?->folio ?? $this->nextFolio(),
                'published_at' => $publishable ? ($current?->published_at ?? $now) : null,
            ];
        })->values()->all();

        $columns = array_values(array_diff(array_keys($payload[0]), ['folio']));

        DB::transaction(fn () => PersonRecord::query()->upsert(
            $payload,
            ['source_victim_id', 'source_report_id', 'source_agency_id'],
            $columns,
        ));
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @param  array<string, PersonRecord>  $existing
     * @param  array<string, int>  $totals
     */
    private function tally(array $rows, array $existing, array &$totals): void
    {
        foreach ($rows as $key => $row) {
            $totals[isset($existing[$key]) ? 'updated' : 'created']++;
            $totals[$row['publishable'] ? 'published' : 'unpublished']++;

            if ($row['photo_path'] !== null) {
                $totals['with_photo']++;
            }
        }
    }

    private function nextFolio(): string
    {
        return sprintf('EN-%06d', ++$this->lastFolioNumber);
    }

    private function sourceKey(string $victimId, int $reportId, int $agencyId): string
    {
        return $victimId.'|'.$reportId.'|'.$agencyId;
    }
}
