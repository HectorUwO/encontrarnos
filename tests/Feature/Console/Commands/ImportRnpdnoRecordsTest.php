<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\MexicanState;
use App\Enums\Sex;
use App\Models\PersonRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportRnpdnoRecordsTest extends TestCase
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
            create table runs (id integer primary key);
            create table reports (
                id integer primary key, run_id integer not null, victim_id text not null, report_id integer not null,
                agency_id integer not null, status text not null, image_status text not null, image_sha256 text
            );
            create table fields (
                report_id integer not null, position integer not null, key text not null, value_json text not null,
                primary key (report_id, position)
            );
            create table images (sha256 text primary key, path text not null);
            insert into runs (id) values (1);
        SQL);
    }

    protected function tearDown(): void
    {
        DB::purge('rnpdno');
        @unlink($this->sourcePath);

        parent::tearDown();
    }

    /**
     * Campos de una ficha típica; los últimos son datos personales que también
     * se guardan (quién los ve lo decide la aplicación).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function ficha(array $overrides = []): array
    {
        return [
            'nombre' => 'MARIA',
            'primerapellido' => 'LOPEZ',
            'segundoapellido' => 'GARCIA',
            'Sexo' => 'MUJER',
            'edadHechos' => 33,
            'edadActual' => 35,
            'estadoHecho' => 'JALISCO ',
            'municipioHecho' => 'ZAPOPAN',
            'ffechahechos' => '15/05/2025',
            'MediaFiliacion' => 'COMPLEXION: ROBUSTA<br>CARA: REDONDO',
            'SanaParticular' => 'TATUAJE',
            'PrendasDeVestir' => 'SIN DATO',
            'EstatusVictima' => 'DESAPARECIDA',
            'PublicarFicha' => 'SI',
            'PertenenciaDependenicaOrigen' => 'COMISION LOCAL DE BUSQUEDA DE PERSONAS DEL ESTADO DE JALISCO',
            'calle' => 'CALLE SECRETA',
            'fechanacimiento' => '01/02/1990',
            'nombreasentamiento' => 'COLONIA SECRETA',
            'lugarnacimiento' => 'LUGAR SECRETO',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function addReport(int $id, array $fields, string $status = 'complete', string $imageStatus = 'saved', ?string $sha256 = null, ?string $victimId = null): void
    {
        $source = DB::connection('rnpdno');

        $source->table('reports')->insert([
            'id' => $id,
            'run_id' => 1,
            'victim_id' => $victimId ?? "VICTIMA-{$id}",
            'report_id' => 1,
            'agency_id' => 44,
            'status' => $status,
            'image_status' => $imageStatus,
            'image_sha256' => $sha256,
        ]);

        $position = 0;

        foreach ($fields as $key => $value) {
            $source->table('fields')->insert([
                'report_id' => $id,
                'position' => $position++,
                'key' => $key,
                'value_json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    private function addImage(string $sha256): string
    {
        $path = 'imagenes/'.substr($sha256, 0, 2)."/{$sha256}.jpg";

        DB::connection('rnpdno')->table('images')->insert(['sha256' => $sha256, 'path' => $path]);

        return $path;
    }

    public function test_imports_a_complete_report_as_a_published_record(): void
    {
        $sha256 = str_repeat('ab', 32);
        $path = $this->addImage($sha256);
        $this->addReport(1, $this->ficha(), sha256: $sha256, victimId: 'BE74EF5E-38B4-4D37-84E0-E49B45504C10');

        $this->artisan('records:import-rnpdno')->assertSuccessful();

        $record = PersonRecord::query()->sole();

        $this->assertSame('EN-000001', $record->folio);
        $this->assertSame('MARIA LOPEZ GARCIA', $record->name);
        $this->assertSame(Sex::Female, $record->sex);
        $this->assertSame(33, $record->age);
        $this->assertSame(35, $record->current_age);
        $this->assertSame(MexicanState::Jalisco, $record->state);
        $this->assertSame('ZAPOPAN', $record->municipality);
        $this->assertSame('2025-05-15', $record->event_date->toDateString());
        $this->assertEquals(['complexion' => 'ROBUSTA', 'cara' => 'REDONDO'], $record->traits);
        $this->assertSame('Complexión: robusta. Cara: redondo. Señas particulares: tatuaje.', $record->description);
        $this->assertNull($record->clothing);
        $this->assertSame('TATUAJE', $record->distinguishing_marks);
        $this->assertSame('COMISION LOCAL DE BUSQUEDA DE PERSONAS DEL ESTADO DE JALISCO', $record->authority);
        $this->assertSame($sha256, $record->photo_sha256);
        $this->assertSame($path, $record->photo_path);
        $this->assertSame('BE74EF5E-38B4-4D37-84E0-E49B45504C10', $record->source_victim_id);
        $this->assertNotNull($record->published_at);
    }

    public function test_imports_the_personal_data_and_the_registry_identifiers(): void
    {
        $this->addReport(1, $this->ficha([
            'Nacionalidad' => 'MEXICANA',
            'iddependenciaorigen' => 57,
            'PertenenciaPorCanalizacion' => 'FISCALIA A|FISCALIA B',
            'fechacaptura' => '5/15/2024',
        ]), victimId: 'BE74EF5E-38B4-4D37-84E0-E49B45504C10');

        $this->artisan('records:import-rnpdno')->assertSuccessful();

        $record = PersonRecord::query()->sole();

        $this->assertSame('CALLE SECRETA', $record->street);
        $this->assertSame('COLONIA SECRETA', $record->neighborhood);
        $this->assertSame('LUGAR SECRETO', $record->birth_place);
        $this->assertSame('1990-01-02', $record->birth_date->toDateString());
        $this->assertSame('MEXICANA', $record->nationality);
        $this->assertSame('2024-05-15', $record->registered_date->toDateString());
        $this->assertSame(['FISCALIA A', 'FISCALIA B'], $record->referred_to);
        $this->assertSame(57, $record->source_authority_id);
        $this->assertSame('BE74EF5E-38B4-4D37-84E0-E49B45504C10', $record->source_victim_id);
        // Los identificadores se guardan, pero el modelo nunca los serializa.
        $this->assertArrayNotHasKey('source_victim_id', $record->toArray());
        $this->assertArrayNotHasKey('source_authority_id', $record->toArray());
    }

    public function test_by_default_only_the_records_authorized_by_the_registry_are_published(): void
    {
        $this->addReport(1, $this->ficha(['PublicarFicha' => 'SI']));
        $this->addReport(2, $this->ficha(['PublicarFicha' => 'SIN DATO']));
        $this->addReport(3, $this->ficha(['PublicarFicha' => 'NO']));

        $this->artisan('records:import-rnpdno')->assertSuccessful();

        $this->assertSame(1, PersonRecord::query()->published()->count());
        $this->assertSame(['NO', 'SI', 'SIN DATO'], PersonRecord::query()->orderBy('registry_publish')->pluck('registry_publish')->all());
    }

    public function test_publishing_all_keeps_what_the_registry_said(): void
    {
        config(['services.records_publish' => 'all']);
        $this->addReport(1, $this->ficha(['PublicarFicha' => 'SI']));
        $this->addReport(2, $this->ficha(['PublicarFicha' => 'SIN DATO']));
        $this->addReport(3, $this->ficha(['PublicarFicha' => 'NO']));

        $this->artisan('records:import-rnpdno')->assertSuccessful();

        $this->assertSame(3, PersonRecord::query()->published()->count());
        $this->assertSame(
            ['NO' => 1, 'SI' => 1, 'SIN DATO' => 1],
            PersonRecord::query()->pluck('registry_publish')->countBy()->sortKeys()->all(),
        );
    }

    public function test_skips_reports_that_are_not_complete(): void
    {
        $this->addReport(1, $this->ficha(), status: 'error');
        $this->addReport(2, $this->ficha(), status: 'pending');

        $this->artisan('records:import-rnpdno')->assertSuccessful();

        $this->assertDatabaseCount('person_records', 0);
    }

    public function test_keeps_a_report_hidden_when_the_registry_does_not_mark_it_publishable(): void
    {
        $this->addReport(1, $this->ficha(['PublicarFicha' => 'SIN DATO']));

        $this->artisan('records:import-rnpdno')->assertSuccessful();

        $this->assertNull(PersonRecord::query()->sole()->published_at);
    }

    public function test_only_links_the_photo_when_the_registry_confirms_it(): void
    {
        $sha256 = str_repeat('cd', 32);
        $this->addImage($sha256);
        $this->addReport(1, $this->ficha(), imageStatus: 'saved_unconfirmed', sha256: $sha256);

        $this->artisan('records:import-rnpdno')->assertSuccessful();

        $this->assertNull(PersonRecord::query()->sole()->photo_path);
    }

    public function test_running_it_twice_updates_records_without_duplicating_them_or_changing_their_folio(): void
    {
        $this->addReport(1, $this->ficha());
        $this->artisan('records:import-rnpdno')->assertSuccessful();
        $firstImport = PersonRecord::query()->sole();

        DB::connection('rnpdno')->table('fields')
            ->where('report_id', 1)->where('key', 'municipioHecho')
            ->update(['value_json' => json_encode('TLAJOMULCO')]);
        $this->travel(1)->day();

        $this->artisan('records:import-rnpdno')->assertSuccessful();

        $secondImport = PersonRecord::query()->sole();

        $this->assertSame($firstImport->id, $secondImport->id);
        $this->assertSame('EN-000001', $secondImport->folio);
        $this->assertSame('TLAJOMULCO', $secondImport->municipality);
        $this->assertTrue($secondImport->published_at->equalTo($firstImport->published_at));
    }

    public function test_new_folios_continue_after_the_highest_existing_one(): void
    {
        PersonRecord::factory()->create(['folio' => 'EN-000005']);
        $this->addReport(1, $this->ficha());

        $this->artisan('records:import-rnpdno')->assertSuccessful();

        $this->assertDatabaseHas('person_records', ['source_victim_id' => 'VICTIMA-1', 'folio' => 'EN-000006']);
    }

    public function test_dry_run_does_not_write_anything(): void
    {
        $this->addReport(1, $this->ficha());

        $this->artisan('records:import-rnpdno --dry-run')->assertSuccessful();

        $this->assertDatabaseCount('person_records', 0);
    }

    public function test_an_import_forgets_the_cached_published_count_but_a_dry_run_keeps_it(): void
    {
        $this->addReport(1, $this->ficha());
        Cache::put(PersonRecord::PUBLISHED_COUNT_KEY, 0);

        $this->artisan('records:import-rnpdno --dry-run')->assertSuccessful();

        $this->assertTrue(Cache::has(PersonRecord::PUBLISHED_COUNT_KEY));

        $this->artisan('records:import-rnpdno')->assertSuccessful();

        $this->assertFalse(Cache::has(PersonRecord::PUBLISHED_COUNT_KEY));
        $this->assertSame(1, PersonRecord::publishedCount());
    }

    public function test_limit_caps_how_many_reports_are_processed(): void
    {
        $this->addReport(1, $this->ficha());
        $this->addReport(2, $this->ficha());
        $this->addReport(3, $this->ficha());

        $this->artisan('records:import-rnpdno --limit=2')->assertSuccessful();

        $this->assertDatabaseCount('person_records', 2);
    }

    public function test_refuses_to_run_while_another_import_is_in_progress(): void
    {
        $this->addReport(1, $this->ficha());
        $lock = Cache::lock('records:import-rnpdno', 60);
        $lock->get();

        $this->artisan('records:import-rnpdno')
            ->expectsOutputToContain('Ya hay una importación en curso.')
            ->assertFailed();

        $this->assertDatabaseCount('person_records', 0);
        $lock->release();
    }

    private function useMeilisearch(): void
    {
        config([
            'services.records_search' => 'meilisearch',
            'services.meilisearch.host' => 'http://meili.test:7700',
            'services.meilisearch.index' => 'fichas',
        ]);

        $task = 0;

        Http::fake(function (Request $request) use (&$task) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                $request->method() === 'GET' && $path === '/indexes/fichas' => Http::response(['uid' => 'fichas']),
                $request->method() === 'GET' && str_starts_with($path, '/tasks/') => Http::response(['status' => 'succeeded']),
                $request->method() === 'GET' && $path === '/indexes/fichas/stats' => Http::response(['numberOfDocuments' => 1]),
                default => Http::response(['taskUid' => ++$task], 202),
            };
        });
    }

    public function test_updates_the_search_index_after_importing_when_meilisearch_is_the_engine(): void
    {
        $this->useMeilisearch();
        $this->addReport(1, $this->ficha());

        $this->artisan('records:import-rnpdno')
            ->expectsOutputToContain('Actualizando el índice de búsqueda')
            ->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/indexes/fichas/documents?primaryKey=id')
            && count($request->data()) === 1);
    }

    public function test_leaves_the_search_index_alone_with_the_no_index_option_a_dry_run_or_the_database_engine(): void
    {
        $this->useMeilisearch();
        $this->addReport(1, $this->ficha());

        $this->artisan('records:import-rnpdno --no-index')->assertSuccessful();
        $this->artisan('records:import-rnpdno --dry-run')->assertSuccessful();

        config(['services.records_search' => 'database']);
        $this->artisan('records:import-rnpdno')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_the_import_still_succeeds_when_the_search_index_cannot_be_updated(): void
    {
        config(['services.records_search' => 'meilisearch']);
        Http::fake(function (): never {
            throw new ConnectionException('No se pudo conectar.');
        });
        $this->addReport(1, $this->ficha());

        $this->artisan('records:import-rnpdno')
            ->expectsOutputToContain('el índice no se actualizó')
            ->assertSuccessful();

        $this->assertDatabaseCount('person_records', 1);
    }
}
