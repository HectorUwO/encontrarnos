<?php

namespace Tests\Feature\Services\Statistics;

use App\Enums\AgeRange;
use App\Enums\DisappearanceStatus;
use App\Enums\MexicanState;
use App\Enums\Sex;
use App\Models\RegistryCount;
use App\Services\Statistics\StatisticsReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StatisticsReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Cinco combinaciones con cifras fáciles de sumar a mano:
     *
     *  - Jalisco, mar 2024, mujer, menor, 10 (Zapopan)
     *  - Jalisco, jun 2025, hombre, adulto, 6 (Zapopan)
     *  - Jalisco, confidencial, 30 (Guadalajara)
     *  - Sinaloa, ago 2024, hombre, 18 a 29, no localizado, 4 (Culiacán)
     *  - Sinaloa, sin fecha, mujer, 40 o más, 3 (Mazatlán)
     *  - Estado desconocido, confidencial, 2
     */
    private function createCounts(): void
    {
        RegistryCount::factory()->inState(MexicanState::Jalisco)->create([
            'month' => '2024-03-01', 'sex' => Sex::Female, 'age_range' => AgeRange::Minor,
            'status' => DisappearanceStatus::Disappeared, 'total' => 10, 'municipality' => 'ZAPOPAN',
        ]);
        RegistryCount::factory()->inState(MexicanState::Jalisco)->create([
            'month' => '2025-06-01', 'sex' => Sex::Male, 'age_range' => AgeRange::Adult,
            'status' => DisappearanceStatus::Disappeared, 'total' => 6, 'municipality' => 'ZAPOPAN',
        ]);
        RegistryCount::factory()->inState(MexicanState::Jalisco)->confidential()->create([
            'total' => 30, 'municipality' => 'GUADALAJARA',
        ]);
        RegistryCount::factory()->inState(MexicanState::Sinaloa)->create([
            'month' => '2024-08-01', 'sex' => Sex::Male, 'age_range' => AgeRange::YoungAdult,
            'status' => DisappearanceStatus::NotLocated, 'total' => 4, 'municipality' => 'CULIACÁN',
        ]);
        RegistryCount::factory()->inState(MexicanState::Sinaloa)->undated()->create([
            'sex' => Sex::Female, 'age_range' => AgeRange::Mature,
            'status' => DisappearanceStatus::Disappeared, 'total' => 3, 'municipality' => 'MAZATLÁN',
        ]);
        RegistryCount::factory()->inState(null)->confidential()->create([
            'total' => 2, 'municipality' => null,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function byKey(array $rows, string $key): array
    {
        return array_column($rows, null, $key);
    }

    public function test_reports_that_there_is_no_data_when_nothing_was_imported(): void
    {
        $this->assertSame(['has_data' => false], (new StatisticsReport)->build());
    }

    public function test_splits_the_registry_into_confidential_dated_and_undated_records(): void
    {
        $this->createCounts();

        $this->assertSame(
            ['registry' => 55, 'confidential' => 32, 'dated' => 20, 'undated' => 3],
            (new StatisticsReport)->build()['totals'],
        );
    }

    public function test_totals_by_state_include_confidential_records_and_rank_the_states(): void
    {
        $this->createCounts();

        $report = (new StatisticsReport)->build();
        $entities = $this->byKey($report['entities'], 'state');

        $this->assertCount(32, $report['entities']);
        $this->assertSame(46, $entities['jalisco']['total']);
        $this->assertSame(1, $entities['jalisco']['rank']);
        $this->assertSame(7, $entities['sinaloa']['total']);
        $this->assertSame(2, $entities['sinaloa']['rank']);
        $this->assertSame(0, $entities['yucatan']['total']);
        $this->assertSame(2, $report['unknown_state']);
    }

    public function test_computes_the_rate_per_hundred_thousand_inhabitants(): void
    {
        RegistryCount::factory()->inState(MexicanState::Colima)->create(['month' => '2024-01-01', 'total' => 731]);

        $colima = $this->byKey((new StatisticsReport)->build()['entities'], 'state')['colima'];

        $this->assertSame(99.9, $colima['per_100k']);
    }

    public function test_reports_how_much_of_each_state_is_confidential(): void
    {
        $this->createCounts();

        $entities = $this->byKey((new StatisticsReport)->build()['entities'], 'state');

        $this->assertSame(30, $entities['jalisco']['confidential']);
        $this->assertSame(46, $entities['jalisco']['registry_total']);
        $this->assertSame(65.2, $entities['jalisco']['confidential_share']);
        $this->assertSame(0.0, $entities['sinaloa']['confidential_share']);
        $this->assertSame(0.0, $entities['yucatan']['confidential_share']);
    }

    public function test_summarizes_the_whole_country_by_default(): void
    {
        $this->createCounts();

        $summary = (new StatisticsReport)->build()['summary'];

        $this->assertSame('México', $summary['scope']);
        $this->assertSame(55, $summary['total']);
        $this->assertSame(100.0, $summary['share']);
        $this->assertNull($summary['rank']);
        $this->assertSame(32, $summary['confidential']);
        $this->assertSame(58.2, $summary['confidential_share']);
        $this->assertSame(['year' => 2024, 'total' => 14], $summary['peak_year']);
    }

    public function test_a_selected_state_narrows_the_summary_timeline_profile_and_municipalities(): void
    {
        $this->createCounts();

        $report = (new StatisticsReport(MexicanState::Jalisco))->build();

        $this->assertSame('Jalisco', $report['summary']['scope']);
        $this->assertSame(46, $report['summary']['total']);
        $this->assertSame(83.6, $report['summary']['share']);
        $this->assertSame(1, $report['summary']['rank']);
        $this->assertSame(0.6, $report['summary']['per_100k']);
        $this->assertSame(
            [['month' => '2024-03', 'total' => 10], ['month' => '2025-06', 'total' => 6]],
            $report['timeline'],
        );
        $this->assertSame(16, $report['profile']['known']);
        $this->assertSame(['Guadalajara', 'Zapopan'], array_column($report['municipalities'], 'name'));
    }

    public function test_a_period_keeps_only_dated_records_inside_it(): void
    {
        $this->createCounts();

        $report = (new StatisticsReport(null, 2025, 2025))->build();
        $entities = $this->byKey($report['entities'], 'state');

        $this->assertSame(6, $entities['jalisco']['total']);
        $this->assertSame(0, $entities['sinaloa']['total']);
        $this->assertSame(0, $report['unknown_state']);
        $this->assertSame(6, $report['summary']['total']);
        $this->assertSame(6, $report['profile']['known']);
        $this->assertSame(['Zapopan'], array_column($report['municipalities'], 'name'));
    }

    public function test_a_period_does_not_change_the_confidential_share_or_the_timeline(): void
    {
        $this->createCounts();

        $report = (new StatisticsReport(null, 2025, 2025))->build();
        $entities = $this->byKey($report['entities'], 'state');

        $this->assertSame(65.2, $entities['jalisco']['confidential_share']);
        $this->assertCount(3, $report['timeline']);
    }

    public function test_a_period_can_have_only_a_start_or_only_an_end(): void
    {
        $this->createCounts();

        $this->assertSame(6, (new StatisticsReport(null, 2025, null))->build()['summary']['total']);
        $this->assertSame(14, (new StatisticsReport(null, null, 2024))->build()['summary']['total']);
    }

    public function test_the_timeline_is_ordered_by_month_and_only_has_dated_records(): void
    {
        $this->createCounts();

        $this->assertSame(
            [
                ['month' => '2024-03', 'total' => 10],
                ['month' => '2024-08', 'total' => 4],
                ['month' => '2025-06', 'total' => 6],
            ],
            (new StatisticsReport)->build()['timeline'],
        );
    }

    public function test_the_profile_counts_only_records_that_report_sex_age_and_status(): void
    {
        $this->createCounts();

        $profile = (new StatisticsReport)->build()['profile'];

        $this->assertSame(23, $profile['known']);
        $this->assertSame(
            ['female' => 13, 'male' => 10, 'unknown' => 0],
            array_column($profile['sex'], 'count', 'value'),
        );
        $this->assertSame(
            ['under-18' => 10, '18-29' => 4, '30-39' => 6, '40-plus' => 3, '' => 0],
            array_column($profile['age'], 'count', 'value'),
        );
        $this->assertSame(
            ['disappeared' => 19, 'not_located' => 4],
            array_column($profile['status'], 'count', 'value'),
        );
    }

    public function test_lists_the_municipalities_with_the_most_records_their_share_and_how_much_is_confidential(): void
    {
        $this->createCounts();

        $municipalities = (new StatisticsReport)->build()['municipalities'];

        // El porcentaje es sobre los 55 registros del alcance, incluidos los 2 sin municipio.
        $this->assertSame(
            [
                ['name' => 'Guadalajara', 'state' => 'jalisco', 'state_label' => 'Jalisco', 'total' => 30, 'share' => 54.5, 'confidential' => 30, 'confidential_share' => 100.0],
                ['name' => 'Zapopan', 'state' => 'jalisco', 'state_label' => 'Jalisco', 'total' => 16, 'share' => 29.1, 'confidential' => 0, 'confidential_share' => 0.0],
                ['name' => 'Culiacán', 'state' => 'sinaloa', 'state_label' => 'Sinaloa', 'total' => 4, 'share' => 7.3, 'confidential' => 0, 'confidential_share' => 0.0],
                ['name' => 'Mazatlán', 'state' => 'sinaloa', 'state_label' => 'Sinaloa', 'total' => 3, 'share' => 5.5, 'confidential' => 0, 'confidential_share' => 0.0],
            ],
            $municipalities,
        );
    }

    public function test_a_municipality_with_confidential_and_dated_records_reports_the_confidential_part(): void
    {
        RegistryCount::factory()->inState(MexicanState::Colima)->create(['municipality' => 'MANZANILLO', 'month' => '2024-01-01', 'total' => 4]);
        RegistryCount::factory()->inState(MexicanState::Colima)->confidential()->create(['municipality' => 'MANZANILLO', 'total' => 6]);

        $manzanillo = (new StatisticsReport(MexicanState::Colima))->build()['municipalities'][0];

        $this->assertSame([10, 6, 60.0], [$manzanillo['total'], $manzanillo['confidential'], $manzanillo['confidential_share']]);
    }

    public function test_a_state_lists_all_its_municipalities_where_the_country_only_shows_ten(): void
    {
        foreach (range(1, 12) as $number) {
            RegistryCount::factory()->inState(MexicanState::Jalisco)->create([
                'municipality' => sprintf('MUNICIPIO %02d', $number), 'total' => $number,
            ]);
        }
        RegistryCount::factory()->inState(MexicanState::Sinaloa)->create(['municipality' => 'CULIACÁN', 'total' => 50]);

        $state = (new StatisticsReport(MexicanState::Jalisco))->build()['municipalities'];

        $this->assertCount(12, $state);
        $this->assertSame(['jalisco'], array_values(array_unique(array_column($state, 'state'))));
        $this->assertSame('Municipio 01', $state[11]['name']);
        $this->assertCount(10, (new StatisticsReport)->build()['municipalities']);
    }

    public function test_counts_the_records_that_do_not_indicate_a_municipality(): void
    {
        RegistryCount::factory()->inState(MexicanState::Veracruz)->create(['municipality' => 'XALAPA', 'total' => 5]);
        RegistryCount::factory()->inState(MexicanState::Veracruz)->confidential()->create(['municipality' => null, 'total' => 3]);
        RegistryCount::factory()->inState(MexicanState::Sinaloa)->confidential()->create(['municipality' => null, 'total' => 2]);

        $this->assertSame(3, (new StatisticsReport(MexicanState::Veracruz))->build()['unknown_municipality']);
        $this->assertSame(5, (new StatisticsReport)->build()['unknown_municipality']);
        $this->assertSame(62.5, (new StatisticsReport(MexicanState::Veracruz))->build()['municipalities'][0]['share']);
    }

    public function test_ranks_the_states_by_rate_as_well_as_by_total(): void
    {
        RegistryCount::factory()->inState(MexicanState::Jalisco)->create(['month' => '2024-01-01', 'total' => 500]);
        RegistryCount::factory()->inState(MexicanState::Colima)->create(['month' => '2024-01-01', 'total' => 100]);

        $entities = $this->byKey((new StatisticsReport)->build()['entities'], 'state');

        $this->assertSame([1, 2], [$entities['jalisco']['rank'], $entities['colima']['rank']]);
        $this->assertSame([2, 1], [$entities['jalisco']['rate_rank'], $entities['colima']['rate_rank']]);
        $this->assertSame(32, max(array_column($entities, 'rate_rank')));
    }

    public function test_the_summary_of_a_state_carries_its_rate_rank_and_the_national_figures_to_compare_with(): void
    {
        $this->createCounts();

        $summary = (new StatisticsReport(MexicanState::Jalisco))->build()['summary'];

        $this->assertSame(MexicanState::Jalisco->population(), $summary['population']);
        $this->assertSame(1, $summary['rate_rank']);
        $this->assertSame(
            ['total' => 55, 'population' => 126_014_024, 'per_100k' => 0.0, 'confidential_share' => 58.2],
            $summary['national'],
        );
        $this->assertNull((new StatisticsReport)->build()['summary']['rate_rank']);
    }

    public function test_limits_the_municipality_ranking_to_ten(): void
    {
        foreach (range(1, 12) as $number) {
            RegistryCount::factory()->inState(MexicanState::Jalisco)->create([
                'municipality' => sprintf('MUNICIPIO %02d', $number), 'total' => $number,
            ]);
        }

        $municipalities = (new StatisticsReport)->build()['municipalities'];

        $this->assertCount(10, $municipalities);
        $this->assertSame('Municipio 12', $municipalities[0]['name']);
        $this->assertSame('Municipio 03', $municipalities[9]['name']);
    }

    public function test_writes_municipality_names_with_lowercase_connectors(): void
    {
        RegistryCount::factory()->inState(MexicanState::Coahuila)->create([
            'municipality' => 'SAN PEDRO DE LAS COLONIAS', 'total' => 1,
        ]);

        $this->assertSame('San Pedro de las Colonias', (new StatisticsReport)->build()['municipalities'][0]['name']);
    }

    public function test_the_cached_report_is_reused_until_it_is_flushed(): void
    {
        $this->createCounts();
        $first = (new StatisticsReport)->cached();

        RegistryCount::query()->delete();

        $this->assertSame($first, (new StatisticsReport)->cached());

        StatisticsReport::flush();

        $this->assertSame(['has_data' => false], (new StatisticsReport)->cached());
    }

    public function test_the_cached_reports_are_kept_apart_by_state(): void
    {
        $this->createCounts();

        $national = (new StatisticsReport)->cached();
        $jalisco = (new StatisticsReport(MexicanState::Jalisco))->cached();

        $this->assertSame(55, $national['summary']['total']);
        $this->assertSame(46, $jalisco['summary']['total']);

        RegistryCount::query()->delete();

        $this->assertSame($national, (new StatisticsReport)->cached());
        $this->assertSame($jalisco, (new StatisticsReport(MexicanState::Jalisco))->cached());
    }

    public function test_reports_for_a_period_are_always_computed_fresh_and_never_stored(): void
    {
        $this->createCounts();

        $this->assertSame(6, (new StatisticsReport(null, 2025, 2025))->cached()['summary']['total']);

        RegistryCount::query()->delete();
        RegistryCount::factory()->inState(MexicanState::Sinaloa)->create(['month' => '2025-02-01', 'total' => 9]);

        $this->assertSame(9, (new StatisticsReport(null, 2025, 2025))->cached()['summary']['total']);
        $this->assertSame(9, (new StatisticsReport(null, 2025))->cached()['summary']['total']);
    }

    public function test_an_empty_report_is_not_cached(): void
    {
        $this->assertSame(['has_data' => false], (new StatisticsReport)->cached());

        $this->createCounts();

        $this->assertTrue((new StatisticsReport)->cached()['has_data']);
    }

    public function test_the_figures_that_do_not_depend_on_the_filters_are_calculated_only_once(): void
    {
        $this->createCounts();

        DB::enableQueryLog();
        (new StatisticsReport)->build();
        $first = count(DB::getQueryLog());
        DB::flushQueryLog();

        (new StatisticsReport(MexicanState::Jalisco, 2024, 2024))->build();
        $second = DB::getQueryLog();

        // Totales del registro, fechas cubiertas y registros por estado no se repiten.
        $this->assertSame($first - 5, count($second));
        $this->assertSame([], array_filter($second, fn (array $query): bool => preg_match('/\b(min|max)\(/i', $query['query']) === 1));
    }

    public function test_warm_prepares_the_report_of_the_country_and_of_every_state(): void
    {
        $this->createCounts();
        $prepared = 0;

        StatisticsReport::warm(function () use (&$prepared): void {
            $prepared++;
        });

        $this->assertSame(33, $prepared);

        DB::enableQueryLog();
        (new StatisticsReport)->cached();
        (new StatisticsReport(MexicanState::Jalisco))->cached();
        (new StatisticsReport(MexicanState::Yucatan))->cached();

        $this->assertSame([], DB::getQueryLog());
    }

    public function test_reports_the_years_and_dates_covered_by_the_data(): void
    {
        $this->createCounts();

        $meta = (new StatisticsReport)->build()['meta'];

        $this->assertSame(2024, $meta['first_year']);
        $this->assertSame(2025, $meta['last_year']);
        $this->assertSame('2025-06', $meta['latest_month']);
        $this->assertSame(now()->toDateString(), $meta['imported_at']);
    }
}
