<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\MexicanState;
use App\Models\RegistryCount;
use App\Services\Statistics\StatisticsReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportRnpdnoStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private string $sourcePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourcePath = tempnam(sys_get_temp_dir(), 'rnpdno');

        config(['database.connections.rnpdno' => [
            'driver' => 'sqlite',
            'database' => $this->sourcePath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        DB::purge('rnpdno');

        DB::connection('rnpdno')->unprepared(<<<'SQL'
            create table runs (id integer primary key, mode text not null);
            create table partitions (id integer primary key, run_id integer not null, state text not null, name text not null);
            create table observations (
                id integer primary key, partition_id integer not null, raw_json text not null, confidential integer not null
            );
        SQL);
    }

    protected function tearDown(): void
    {
        DB::purge('rnpdno');
        @unlink($this->sourcePath);

        parent::tearDown();
    }

    private function addRun(int $id, string $mode): void
    {
        DB::connection('rnpdno')->table('runs')->insert(['id' => $id, 'mode' => $mode]);
    }

    private function addPartition(int $id, int $runId, string $state, string $name): void
    {
        DB::connection('rnpdno')->table('partitions')->insert([
            'id' => $id, 'run_id' => $runId, 'state' => $state, 'name' => $name,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function addObservation(int $partitionId, bool $confidential = false, array $overrides = []): void
    {
        DB::connection('rnpdno')->table('observations')->insert([
            'partition_id' => $partitionId,
            'raw_json' => json_encode([
                'Sexo' => 'MUJER',
                'ffechahechos' => '15/05/2025',
                'fechanacimiento' => '1990-01-01T00:00:00.000Z',
                'EstatusVictima' => 'DESAPARECIDA',
                'nombre' => 'MARIA',
                ...$overrides,
            ]),
            'confidential' => (int) $confidential,
        ]);
    }

    public function test_summarizes_the_latest_full_run_into_counts(): void
    {
        $this->addRun(1, 'sample');
        $this->addPartition(1, 1, '18', 'TEPIC');
        $this->addObservation(1);

        $this->addRun(2, 'full');
        $this->addPartition(2, 2, '14', 'ZAPOPAN');
        $this->addObservation(2);
        $this->addObservation(2, overrides: ['ffechahechos' => '20/05/2025']);
        $this->addObservation(2, confidential: true);
        $this->addPartition(3, 2, '33', 'SE DESCONOCE');
        $this->addObservation(3, confidential: true);

        $this->artisan('records:import-rnpdno-statistics')
            ->expectsOutputToContain('Registros del listado')
            ->assertSuccessful();

        $this->assertDatabaseCount('registry_counts', 3);
        $this->assertDatabaseHas('registry_counts', [
            'state' => 'jalisco', 'municipality' => 'ZAPOPAN', 'month' => '2025-05-01', 'sex' => 'female',
            'age_range' => '30-39', 'status' => 'disappeared', 'confidential' => 0, 'total' => 2,
        ]);
        $this->assertDatabaseHas('registry_counts', [
            'state' => 'jalisco', 'municipality' => 'ZAPOPAN', 'month' => null, 'sex' => null, 'confidential' => 1, 'total' => 1,
        ]);
        $this->assertDatabaseHas('registry_counts', [
            'state' => null, 'municipality' => null, 'confidential' => 1, 'total' => 1,
        ]);
        $this->assertDatabaseMissing('registry_counts', ['municipality' => 'TEPIC']);
    }

    public function test_replaces_the_counts_of_a_previous_import(): void
    {
        RegistryCount::factory()->count(3)->create(['municipality' => 'VIEJO']);
        $this->addRun(1, 'full');
        $this->addPartition(1, 1, '14', 'ZAPOPAN');
        $this->addObservation(1);

        $this->artisan('records:import-rnpdno-statistics')->assertSuccessful();

        $this->assertDatabaseCount('registry_counts', 1);
        $this->assertDatabaseMissing('registry_counts', ['municipality' => 'VIEJO']);
    }

    public function test_leaves_the_existing_counts_untouched_when_the_collector_has_no_full_run(): void
    {
        RegistryCount::factory()->count(2)->create();
        $this->addRun(1, 'sample');
        $this->addPartition(1, 1, '14', 'ZAPOPAN');
        $this->addObservation(1);

        $this->artisan('records:import-rnpdno-statistics')
            ->expectsOutputToContain('El colector no tiene una corrida completa')
            ->assertFailed();

        $this->assertDatabaseCount('registry_counts', 2);
    }

    public function test_dry_run_does_not_write_anything(): void
    {
        $this->addRun(1, 'full');
        $this->addPartition(1, 1, '14', 'ZAPOPAN');
        $this->addObservation(1);

        $this->artisan('records:import-rnpdno-statistics --dry-run')->assertSuccessful();

        $this->assertDatabaseCount('registry_counts', 0);
    }

    public function test_an_import_discards_the_cached_reports_but_a_dry_run_keeps_them(): void
    {
        RegistryCount::factory()->inState(MexicanState::Colima)->create(['month' => '2024-01-01', 'total' => 5]);
        $stale = (new StatisticsReport)->cached();
        $this->addRun(1, 'full');
        $this->addPartition(1, 1, '14', 'ZAPOPAN');
        $this->addObservation(1);

        $this->artisan('records:import-rnpdno-statistics --dry-run')->assertSuccessful();

        $this->assertSame($stale, (new StatisticsReport)->cached());

        $this->artisan('records:import-rnpdno-statistics')->assertSuccessful();

        $this->assertSame(1, (new StatisticsReport)->cached()['summary']['total']);
    }

    public function test_an_import_leaves_the_reports_of_the_country_and_of_every_state_ready(): void
    {
        $this->addRun(1, 'full');
        $this->addPartition(1, 1, '14', 'ZAPOPAN');
        $this->addObservation(1);

        $this->artisan('records:import-rnpdno-statistics')
            ->expectsOutputToContain('Precalculando las estadísticas')
            ->assertSuccessful();

        DB::enableQueryLog();
        (new StatisticsReport)->cached();
        (new StatisticsReport(MexicanState::Jalisco))->cached();
        (new StatisticsReport(MexicanState::Sonora))->cached();

        $this->assertSame([], DB::getQueryLog());
    }

    public function test_refuses_to_run_while_another_import_is_in_progress(): void
    {
        $this->addRun(1, 'full');
        $this->addPartition(1, 1, '14', 'ZAPOPAN');
        $this->addObservation(1);
        $lock = Cache::lock('records:import-rnpdno-statistics', 60);
        $lock->get();

        $this->artisan('records:import-rnpdno-statistics')
            ->expectsOutputToContain('Ya hay una importación en curso.')
            ->assertFailed();

        $this->assertDatabaseCount('registry_counts', 0);
        $lock->release();
    }
}
