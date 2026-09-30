<?php

namespace Tests\Feature\Models;

use App\Enums\MexicanState;
use App\Models\PersonRecord;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La búsqueda de texto completo solo existe en MySQL. Estas pruebas se saltan
 * con SQLite y solo corren contra una base MySQL exclusiva para pruebas (su
 * nombre debe terminar en «_testing», porque la migran y la vacían):
 *
 *     DB_CONNECTION=mysql DB_DATABASE=encontrarnos_testing php artisan test --filter=FullText
 *
 * No usan transacciones (RefreshDatabase): InnoDB solo ve en el índice de texto
 * completo lo que ya se confirmó.
 */
class PersonRecordFullTextTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.records_fulltext' => true]);
    }

    protected function tearDown(): void
    {
        // Sin esto las filas confirmadas se le quedan a las pruebas que siguen.
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    protected function beforeTruncatingDatabase(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'mysql' || ! str_ends_with($connection->getDatabaseName(), '_testing')) {
            $this->markTestSkipped('Necesita una base MySQL de pruebas (nombre terminado en «_testing»).');
        }
    }

    /**
     * @return list<string>
     */
    private function found(string $term): array
    {
        return PersonRecord::query()->published()->search($term)->orderBy('folio')->pluck('folio')->all();
    }

    public function test_finds_words_by_their_beginning_ignoring_case_and_accents(): void
    {
        PersonRecord::factory()->create(['folio' => 'EN-000001', 'name' => 'MARÍA LUCERO PAREDES']);
        PersonRecord::factory()->create(['folio' => 'EN-000002', 'name' => 'MARTIN ROJAS']);
        PersonRecord::factory()->create(['folio' => 'EN-000003', 'name' => 'JUAN PEREZ']);

        $this->assertSame(['EN-000001'], $this->found('maria'));
        $this->assertSame(['EN-000001'], $this->found('MARÍA'));
        $this->assertSame(['EN-000001', 'EN-000002'], $this->found('mar'));
    }

    public function test_requires_every_word_in_any_order(): void
    {
        PersonRecord::factory()->create(['folio' => 'EN-000001', 'name' => 'MARIA LUCERO PAREDES']);
        PersonRecord::factory()->create(['folio' => 'EN-000002', 'name' => 'MARIA FERNANDA RUIZ']);

        $this->assertSame(['EN-000001'], $this->found('paredes maria'));
        $this->assertSame([], $this->found('lucero ruiz'));
    }

    public function test_looks_in_the_municipality_the_description_and_the_state(): void
    {
        PersonRecord::factory()->create(['folio' => 'EN-000001', 'name' => 'ANA', 'municipality' => 'TLAJOMULCO DE ZÚÑIGA', 'state' => MexicanState::Jalisco, 'description' => 'Cabello: negro.']);
        PersonRecord::factory()->create(['folio' => 'EN-000002', 'name' => 'LUIS', 'municipality' => 'TEPIC', 'state' => MexicanState::Nayarit, 'description' => 'Ojos: verdes.']);
        PersonRecord::factory()->create(['folio' => 'EN-000003', 'name' => 'ROSA', 'municipality' => 'LEÓN', 'state' => MexicanState::Guanajuato, 'description' => 'Ojos: cafés.']);
        PersonRecord::factory()->create(['folio' => 'EN-000004', 'name' => 'PEDRO', 'municipality' => 'MONTERREY', 'state' => MexicanState::NuevoLeon, 'description' => 'Ojos: cafés.']);

        $this->assertSame(['EN-000001'], $this->found('tlajomulco'));
        $this->assertSame(['EN-000002'], $this->found('verdes'));
        $this->assertSame(['EN-000001'], $this->found('jalisco'));
        $this->assertSame(['EN-000003'], $this->found('leon guanajuato'));
        $this->assertSame(['EN-000004'], $this->found('nuevo leon'));
    }

    public function test_a_folio_or_a_number_is_looked_up_in_the_folio(): void
    {
        PersonRecord::factory()->create(['folio' => 'EN-000123', 'name' => 'ANA']);
        PersonRecord::factory()->create(['folio' => 'EN-000456', 'name' => 'LUIS']);

        $this->assertSame(['EN-000123'], $this->found('EN-000123'));
        $this->assertSame(['EN-000456'], $this->found('456'));
        $this->assertSame(['EN-000123'], $this->found('ana en-000123'));
    }

    public function test_short_words_such_as_articles_only_count_when_they_are_all_there_is(): void
    {
        PersonRecord::factory()->create(['folio' => 'EN-000001', 'name' => 'JUAN DE LA CRUZ']);
        PersonRecord::factory()->create(['folio' => 'EN-000002', 'name' => 'JUAN PEREZ']);

        $this->assertSame(['EN-000001'], $this->found('juan de la cruz'));
        $this->assertContains('EN-000001', $this->found('de'));
    }

    public function test_symbols_are_not_treated_as_search_operators(): void
    {
        PersonRecord::factory()->create(['folio' => 'EN-000001', 'name' => 'MARIA LOPEZ']);

        $this->assertSame(['EN-000001'], $this->found('+maria* -lopez ("'));
        $this->assertSame([], $this->found('"+-*~()'));
    }

    public function test_hidden_records_are_never_found(): void
    {
        PersonRecord::factory()->unpublished()->create(['name' => 'MARIA OCULTA']);

        $this->assertSame([], $this->found('maria'));
    }
}
