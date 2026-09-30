<?php

namespace Tests\Unit\Services\Rnpdno;

use App\Services\Rnpdno\RnpdnoListingAggregator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RnpdnoListingAggregatorTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function record(array $overrides = []): array
    {
        return [
            'Sexo' => 'MUJER',
            'ffechahechos' => '15/05/2025',
            'fechahechos' => '2025-05-15T06:00:00.000Z',
            'fechanacimiento' => '1990-01-01T00:00:00.000Z',
            'EstatusVictima' => 'DESAPARECIDA',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function single(array $fields, int $stateCode = 14, string $municipality = 'ZAPOPAN', bool $confidential = false): array
    {
        $aggregator = new RnpdnoListingAggregator;
        $aggregator->add($stateCode, $municipality, $confidential, $fields);

        $rows = $aggregator->rows();
        $this->assertCount(1, $rows);

        return $rows[0];
    }

    public function test_counts_a_record_by_state_municipality_month_sex_age_and_status(): void
    {
        $this->assertSame(
            [
                'state' => 'jalisco',
                'municipality' => 'ZAPOPAN',
                'month' => '2025-05-01',
                'sex' => 'female',
                'age_range' => '30-39',
                'status' => 'disappeared',
                'confidential' => false,
                'total' => 1,
            ],
            $this->single($this->record()),
        );
    }

    public function test_confidential_records_only_keep_the_state_and_the_municipality(): void
    {
        $row = $this->single($this->record(), confidential: true);

        $this->assertSame('jalisco', $row['state']);
        $this->assertSame('ZAPOPAN', $row['municipality']);
        $this->assertTrue($row['confidential']);
        $this->assertNull($row['month']);
        $this->assertNull($row['sex']);
        $this->assertNull($row['age_range']);
        $this->assertNull($row['status']);
    }

    public function test_a_record_whose_own_fields_say_confidential_is_treated_as_confidential(): void
    {
        $row = $this->single($this->record(['Sexo' => 'CONFIDENCIAL']));

        $this->assertTrue($row['confidential']);
        $this->assertNull($row['sex']);
    }

    public function test_merges_records_that_share_the_same_combination(): void
    {
        $aggregator = new RnpdnoListingAggregator;
        $aggregator->add(14, 'ZAPOPAN', false, $this->record());
        $aggregator->add(14, 'ZAPOPAN', false, $this->record(['ffechahechos' => '20/05/2025']));
        $aggregator->add(14, 'ZAPOPAN', false, $this->record(['Sexo' => 'HOMBRE']));

        $totals = array_column($aggregator->rows(), 'total', 'sex');

        $this->assertSame(['female' => 2, 'male' => 1], $totals);
    }

    #[DataProvider('ageBoundaries')]
    public function test_computes_the_age_completed_on_the_day_of_the_events(string $event, string $birth, ?string $expected): void
    {
        $row = $this->single($this->record(['ffechahechos' => $event, 'fechanacimiento' => $birth]));

        $this->assertSame($expected, $row['age_range']);
    }

    /**
     * @return array<string, array{string, string, string|null}>
     */
    public static function ageBoundaries(): array
    {
        return [
            'the day before the 30th birthday' => ['14/06/2020', '1990-06-15T00:00:00.000Z', '18-29'],
            'on the 30th birthday' => ['15/06/2020', '1990-06-15T00:00:00.000Z', '30-39'],
            'a minor' => ['15/06/2020', '2010-01-01T00:00:00.000Z', 'under-18'],
            'born after the events' => ['15/06/2020', '2021-01-01T00:00:00.000Z', null],
            'older than 110' => ['15/06/2020', '1900-01-01T00:00:00.000Z', null],
            'unreadable birth date' => ['15/06/2020', 'SIN DATO', null],
        ];
    }

    public function test_the_age_is_unknown_when_the_date_of_the_events_is_unknown(): void
    {
        $row = $this->single($this->record(['ffechahechos' => 'SIN DATO', 'fechahechos' => '']));

        $this->assertNull($row['month']);
        $this->assertNull($row['age_range']);
    }

    #[DataProvider('eventDates')]
    public function test_reads_the_month_of_the_events(array $fields, ?string $expected): void
    {
        $this->assertSame($expected, $this->single($this->record($fields))['month']);
    }

    /**
     * @return array<string, array{array<string, string>, string|null}>
     */
    public static function eventDates(): array
    {
        return [
            'day first' => [['ffechahechos' => '31/01/2024'], '2024-01-01'],
            'iso when the formatted date is missing' => [['ffechahechos' => 'SIN DATO', 'fechahechos' => '2023-11-05T06:00:00.000Z'], '2023-11-01'],
            'day that does not exist' => [['ffechahechos' => '31/02/2025', 'fechahechos' => ''], null],
            'implausibly old' => [['ffechahechos' => '15/05/1850', 'fechahechos' => ''], null],
            'no data' => [['ffechahechos' => 'SIN DATO', 'fechahechos' => ''], null],
        ];
    }

    public function test_the_unknown_state_of_the_registry_is_not_a_state(): void
    {
        $this->assertNull($this->single($this->record(), stateCode: 33)['state']);
    }

    #[DataProvider('unknownMunicipalities')]
    public function test_unknown_municipalities_are_left_empty(string $name): void
    {
        $this->assertNull($this->single($this->record(), municipality: $name)['municipality']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unknownMunicipalities(): array
    {
        return [
            'unknown' => ['SE DESCONOCE'],
            'no data' => ['SIN DATO'],
            'confidential' => ['CONFIDENCIAL'],
            'blank' => ['   '],
        ];
    }

    public function test_tidies_the_municipality_name(): void
    {
        $this->assertSame('SAN PEDRO TLAQUEPAQUE', $this->single($this->record(), municipality: '  SAN   PEDRO TLAQUEPAQUE ')['municipality']);
    }

    public function test_interprets_the_sex_and_status_used_by_the_registry(): void
    {
        $row = $this->single($this->record(['Sexo' => 'INDETERMINADO', 'EstatusVictima' => 'NO LOCALIZADA']));

        $this->assertSame('unknown', $row['sex']);
        $this->assertSame('not_located', $row['status']);
    }

    public function test_does_not_keep_any_personal_data(): void
    {
        $row = $this->single($this->record(['nombre' => 'MARIA', 'primerapellido' => 'LOPEZ', 'IDvictimadirecta' => 'BE74EF5E-38B4']));

        $this->assertStringNotContainsString('MARIA', json_encode($row));
        $this->assertStringNotContainsString('LOPEZ', json_encode($row));
        $this->assertStringNotContainsString('BE74EF5E', json_encode($row));
    }
}
