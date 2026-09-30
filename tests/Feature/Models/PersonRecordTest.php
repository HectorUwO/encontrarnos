<?php

namespace Tests\Feature\Models;

use App\Enums\AgeRange;
use App\Enums\MexicanState;
use App\Models\PersonRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PersonRecordTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function foliosFound(?string $term): array
    {
        return PersonRecord::query()->search($term)->orderBy('folio')->pluck('folio')->all();
    }

    public function test_published_scope_excludes_hidden_records(): void
    {
        PersonRecord::factory()->create(['folio' => 'EN-000001']);
        PersonRecord::factory()->unpublished()->create(['folio' => 'EN-000002']);

        $this->assertSame(['EN-000001'], PersonRecord::query()->published()->pluck('folio')->all());
    }

    public function test_search_requires_every_word_but_ignores_their_order(): void
    {
        PersonRecord::factory()->create(['folio' => 'EN-000001', 'name' => 'MARIA LUCERO PAREDES']);
        PersonRecord::factory()->create(['folio' => 'EN-000002', 'name' => 'MARIA FERNANDA RUIZ']);

        $this->assertSame(['EN-000001'], $this->foliosFound('paredes maria'));
        $this->assertSame(['EN-000001'], $this->foliosFound('LUCERO'));
        $this->assertSame([], $this->foliosFound('lucero ruiz'));
    }

    public function test_search_looks_in_folio_municipality_and_description(): void
    {
        PersonRecord::factory()->create(['folio' => 'EN-000010', 'name' => 'A', 'municipality' => 'ZAPOPAN', 'description' => 'Complexión: robusta.']);
        PersonRecord::factory()->create(['folio' => 'EN-000011', 'name' => 'B', 'municipality' => 'TEPIC', 'description' => 'Señas particulares: tatuaje.']);

        $this->assertSame(['EN-000010'], $this->foliosFound('en-000010'));
        $this->assertSame(['EN-000010'], $this->foliosFound('zapopan'));
        $this->assertSame(['EN-000011'], $this->foliosFound('tatuaje'));
    }

    public function test_search_matches_the_name_of_the_state(): void
    {
        PersonRecord::factory()->inState(MexicanState::NuevoLeon)->create(['folio' => 'EN-000001', 'name' => 'A', 'municipality' => 'X', 'description' => 'x']);
        PersonRecord::factory()->inState(MexicanState::Sinaloa)->create(['folio' => 'EN-000002', 'name' => 'B', 'municipality' => 'X', 'description' => 'x']);

        $this->assertSame(['EN-000001'], $this->foliosFound('nuevo leon'));
    }

    public function test_search_treats_wildcard_characters_literally(): void
    {
        PersonRecord::factory()->create(['folio' => 'EN-000001', 'name' => 'ANA', 'municipality' => 'X', 'description' => 'sin porcentajes']);

        $this->assertSame([], $this->foliosFound('%'));
        $this->assertSame([], $this->foliosFound('_'));
    }

    #[DataProvider('blankTerms')]
    public function test_search_without_words_returns_every_record(?string $term): void
    {
        PersonRecord::factory()->count(2)->create();

        $this->assertCount(2, $this->foliosFound($term));
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function blankTerms(): array
    {
        return ['null' => [null], 'empty' => [''], 'only spaces' => ['   ']];
    }

    #[DataProvider('ageBoundaries')]
    public function test_age_range_scope_includes_both_limits(AgeRange $range, int $age, bool $isIncluded): void
    {
        PersonRecord::factory()->ofAge($age)->create();

        $this->assertSame($isIncluded, PersonRecord::query()->inAgeRange($range)->exists());
    }

    /**
     * @return array<string, array{AgeRange, int, bool}>
     */
    public static function ageBoundaries(): array
    {
        return [
            'minor lower limit' => [AgeRange::Minor, 0, true],
            'minor upper limit' => [AgeRange::Minor, 17, true],
            'eighteen is not a minor' => [AgeRange::Minor, 18, false],
            'young adult lower limit' => [AgeRange::YoungAdult, 18, true],
            'young adult upper limit' => [AgeRange::YoungAdult, 29, true],
            'adult lower limit' => [AgeRange::Adult, 30, true],
            'adult upper limit' => [AgeRange::Adult, 39, true],
            'forty is not an adult range' => [AgeRange::Adult, 40, false],
            'mature has no upper limit' => [AgeRange::Mature, 110, true],
        ];
    }

    public function test_age_range_scope_excludes_records_without_age(): void
    {
        PersonRecord::factory()->ofAge(null)->create();

        $this->assertFalse(PersonRecord::query()->inAgeRange(AgeRange::Minor)->exists());
    }

    public function test_scopes_without_a_value_do_not_filter(): void
    {
        PersonRecord::factory()->count(2)->create();

        $this->assertSame(2, PersonRecord::query()->inState(null)->inAgeRange(null)->ofType(null)->count());
    }

    public function test_latest_events_orders_by_date_and_puts_undated_records_last(): void
    {
        PersonRecord::factory()->create(['folio' => 'EN-000001', 'event_date' => '2026-01-01']);
        PersonRecord::factory()->create(['folio' => 'EN-000002', 'event_date' => null]);
        PersonRecord::factory()->create(['folio' => 'EN-000003', 'event_date' => '2026-06-01']);
        PersonRecord::factory()->create(['folio' => 'EN-000004', 'event_date' => '2026-06-01']);

        $this->assertSame(
            ['EN-000004', 'EN-000003', 'EN-000001', 'EN-000002'],
            PersonRecord::query()->latestEvents()->pluck('folio')->all(),
        );
    }

    public function test_published_count_ignores_hidden_records_and_is_cached_until_forgotten(): void
    {
        PersonRecord::factory()->count(2)->create();
        PersonRecord::factory()->unpublished()->create();

        $this->assertSame(2, PersonRecord::publishedCount());

        PersonRecord::factory()->create();

        $this->assertSame(2, PersonRecord::publishedCount());

        Cache::forget(PersonRecord::PUBLISHED_COUNT_KEY);

        $this->assertSame(3, PersonRecord::publishedCount());
    }

    public function test_orders_the_traits_the_way_they_are_read_whatever_order_they_were_saved_in(): void
    {
        $record = PersonRecord::factory()->create([
            'traits' => ['peso' => '65KG', 'otro_rasgo' => 'X', 'ojos' => 'CAFÉS', 'cabello' => 'NEGRO', 'complexion' => 'MEDIA', 'cara' => 'OVALADO'],
        ]);

        $this->assertSame(
            ['complexion', 'cara', 'cabello', 'ojos', 'peso', 'otro_rasgo'],
            array_keys($record->fresh()->orderedTraits()),
        );
        $this->assertSame([], PersonRecord::factory()->create(['traits' => null])->orderedTraits());
    }

    public function test_source_identifiers_are_hidden_when_serialized(): void
    {
        $record = PersonRecord::factory()->create([
            'source_victim_id' => 'BE74EF5E-38B4-4D37-84E0-E49B45504C10',
            'source_report_id' => 1,
            'source_agency_id' => 44,
        ]);

        $this->assertArrayNotHasKey('source_victim_id', $record->toArray());
        $this->assertArrayNotHasKey('source_report_id', $record->toArray());
        $this->assertArrayNotHasKey('source_agency_id', $record->toArray());
    }
}
