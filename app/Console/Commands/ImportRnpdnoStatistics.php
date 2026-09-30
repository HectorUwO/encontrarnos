<?php

namespace App\Console\Commands;

use App\Enums\MexicanState;
use App\Models\RegistryCount;
use App\Services\Rnpdno\RnpdnoListingAggregator;
use App\Services\Rnpdno\RnpdnoSource;
use App\Services\Statistics\StatisticsReport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

#[Signature('records:import-rnpdno-statistics {--dry-run : Calcula el resumen sin escribir en la base}')]
#[Description('Resume el listado completo del colector RNPDNO en conteos por estado, municipio, mes, sexo y edad')]
class ImportRnpdnoStatistics extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RnpdnoSource $source, RnpdnoListingAggregator $aggregator): int
    {
        $lock = Cache::lock('records:import-rnpdno-statistics', 3600);

        if (! $lock->get()) {
            $this->components->error('Ya hay una importación en curso.');

            return self::FAILURE;
        }

        try {
            return $this->import($source, $aggregator);
        } finally {
            $lock->release();
        }
    }

    private function import(RnpdnoSource $source, RnpdnoListingAggregator $aggregator): int
    {
        $processed = 0;

        foreach ($source->listing() as $entry) {
            $aggregator->add($entry['state_code'], $entry['municipality'], $entry['confidential'], $entry['fields']);
            $processed++;
        }

        if ($processed === 0) {
            $this->components->error('El colector no tiene una corrida completa que resumir; no se modificó nada.');

            return self::FAILURE;
        }

        $rows = $aggregator->rows();

        if (! $this->option('dry-run')) {
            $this->store($rows);
            StatisticsReport::flush();
            $this->warmReports();
        }

        $confidential = $this->sum($rows, fn (array $row): bool => $row['confidential']);
        $undated = $this->sum($rows, fn (array $row): bool => ! $row['confidential'] && $row['month'] === null);

        $this->components->twoColumnDetail('Registros del listado', number_format($processed));
        $this->components->twoColumnDetail('Confidenciales (solo estado y municipio)', number_format($confidential));
        $this->components->twoColumnDetail('Sin fecha de los hechos', number_format($undated));
        $this->components->twoColumnDetail('Combinaciones guardadas', number_format(count($rows)).($this->option('dry-run') ? ' (simulación)' : ''));

        return self::SUCCESS;
    }

    /**
     * Deja listos los informes del país y de cada estado, para que nadie espere
     * el primer cálculo después de una importación.
     */
    private function warmReports(): void
    {
        $this->components->info('Precalculando las estadísticas…');

        $bar = $this->output->createProgressBar(count(MexicanState::cases()) + 1);
        $bar->start();

        StatisticsReport::warm(fn () => $bar->advance());

        $bar->finish();
        $this->newLine(2);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function store(array $rows): void
    {
        $now = now();

        DB::transaction(function () use ($rows, $now): void {
            RegistryCount::query()->delete();

            foreach (array_chunk($rows, 1000) as $chunk) {
                RegistryCount::query()->insert(array_map(
                    fn (array $row): array => [...$row, 'created_at' => $now, 'updated_at' => $now],
                    $chunk,
                ));
            }
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): bool  $matches
     */
    private function sum(array $rows, callable $matches): int
    {
        return array_sum(array_map(fn (array $row): int => $matches($row) ? $row['total'] : 0, $rows));
    }
}
