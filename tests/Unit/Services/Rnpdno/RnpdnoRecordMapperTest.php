<?php

namespace Tests\Unit\Services\Rnpdno;

use App\Services\Rnpdno\RnpdnoRecordMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RnpdnoRecordMapperTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function map(array $fields): array
    {
        return (new RnpdnoRecordMapper)->map($fields);
    }

    #[DataProvider('ages')]
    public function test_parses_the_age_and_discards_impossible_values(mixed $value, ?int $expected): void
    {
        $this->assertSame($expected, $this->map(['edadHechos' => $value])['age']);
    }

    /**
     * @return array<string, array{mixed, int|null}>
     */
    public static function ages(): array
    {
        return [
            'integer' => [33, 33],
            'numeric text' => ['33', 33],
            'newborn' => [0, 0],
            'oldest accepted' => [110, 110],
            'age inside a sentence' => ['Edad capturada en el sistema 15', 15],
            'just above the limit' => [111, null],
            'a year typed as age' => [1815, null],
            'negative' => [-3, null],
            'no birth date' => ['SIN FECHA DE NACIMIENTO', null],
            'missing' => [null, null],
        ];
    }

    public function test_falls_back_to_the_age_in_years_when_the_age_at_the_events_is_unusable(): void
    {
        $attributes = $this->map(['edadHechos' => 'SIN DATO', 'edadanios' => 28, 'edadActual' => 'SIN FECHA DE NACIMIENTO']);

        $this->assertSame(28, $attributes['age']);
        $this->assertNull($attributes['current_age']);
    }

    #[DataProvider('dates')]
    public function test_parses_the_date_of_the_events(array $fields, ?string $expected): void
    {
        $this->assertSame($expected, $this->map($fields)['event_date']);
    }

    /**
     * @return array<string, array{array<string, string>, string|null}>
     */
    public static function dates(): array
    {
        return [
            'day first' => [['ffechahechos' => '15/05/2025'], '2025-05-15'],
            'no data' => [['ffechahechos' => 'SIN DATO'], null],
            'day that does not exist' => [['ffechahechos' => '31/02/2025'], null],
            'implausibly old' => [['ffechahechos' => '15/05/1850'], null],
            'implausibly far in the future' => [['ffechahechos' => '15/05/2999'], null],
            'month first when the formatted date is missing' => [['ffechahechos' => 'SIN DATO', 'fechahechos' => '5/15/2025'], '2025-05-15'],
        ];
    }

    public function test_builds_the_name_from_the_parts_that_have_data(): void
    {
        $this->assertSame('MARIA LOPEZ', $this->map([
            'nombre' => 'MARIA',
            'primerapellido' => 'LOPEZ',
            'segundoapellido' => 'SIN DATO',
        ])['name']);

        $this->assertNull($this->map(['nombre' => 'SIN DATO', 'primerapellido' => '*'])['name']);
    }

    public function test_parses_the_physical_traits_separated_by_line_breaks(): void
    {
        $attributes = $this->map(['MediaFiliacion' => 'COMPLEXION: ROBUSTA<br>COLOR DE LA PIEL: BLANCO<br>ESTATURA: 163cm']);

        $this->assertSame(
            ['complexion' => 'ROBUSTA', 'color_de_la_piel' => 'BLANCO', 'estatura' => '163cm'],
            json_decode($attributes['traits'], true),
        );
    }

    public function test_parses_the_physical_traits_separated_by_commas(): void
    {
        $attributes = $this->map(['MediaFiliacion' => 'COMPLEXION: ROBUSTA, CARA: REDONDO, COLOR DE LA PIEL: BLANCO']);

        $this->assertSame(
            ['complexion' => 'ROBUSTA', 'cara' => 'REDONDO', 'color_de_la_piel' => 'BLANCO'],
            json_decode($attributes['traits'], true),
        );
    }

    public function test_writes_a_readable_description(): void
    {
        $attributes = $this->map([
            'MediaFiliacion' => 'COMPLEXION: ROBUSTA<br>COLOR DE LA PIEL: BLANCO',
            'SanaParticular' => 'TATUAJE',
            'PrendasDeVestir' => 'PLAYERA ROJA',
        ]);

        $this->assertSame(
            'Complexión: robusta. Color de piel: blanco. Señas particulares: tatuaje. Prendas de vestir: playera roja.',
            $attributes['description'],
        );
    }

    public function test_leaves_the_description_empty_when_the_registry_has_no_information(): void
    {
        $attributes = $this->map(['MediaFiliacion' => 'SIN DATO', 'SanaParticular' => 'SIN DATO', 'PrendasDeVestir' => '*']);

        $this->assertNull($attributes['description']);
        $this->assertNull($attributes['traits']);
        $this->assertNull($attributes['clothing']);
        $this->assertNull($attributes['distinguishing_marks']);
    }

    public function test_treats_unknown_places_as_missing(): void
    {
        $attributes = $this->map(['estadoHecho' => 'SE DESCONOCE', 'municipioHecho' => 'SIN DATO']);

        $this->assertNull($attributes['state']);
        $this->assertNull($attributes['municipality']);
    }

    #[DataProvider('publishableValues')]
    public function test_only_reports_marked_yes_are_publishable(?string $value, bool $expected): void
    {
        $this->assertSame($expected, (new RnpdnoRecordMapper)->isPublishable(['PublicarFicha' => $value]));
    }

    /**
     * @return array<string, array{string|null, bool}>
     */
    public static function publishableValues(): array
    {
        return [
            'yes' => ['SI', true],
            'yes with spaces and lowercase' => [' si ', true],
            'no data' => ['SIN DATO', false],
            'no' => ['NO', false],
            'missing' => [null, false],
        ];
    }

    public function test_keeps_the_addresses_and_birth_data_the_registry_provides(): void
    {
        $attributes = $this->map([
            'nombre' => 'MARIA',
            'calle' => 'CALLE UNO',
            'noexterior' => '12',
            'nointerior' => 'B',
            'codigopostal' => '63000',
            'nombreasentamiento' => 'CENTRO',
            'fechanacimiento' => '5/8/1990',
            'estadonacimiento' => 'NAYARIT',
            'lugarnacimiento' => 'TEPIC',
        ]);

        $this->assertSame('CALLE UNO', $attributes['street']);
        $this->assertSame('12', $attributes['exterior_number']);
        $this->assertSame('B', $attributes['interior_number']);
        $this->assertSame('63000', $attributes['postal_code']);
        $this->assertSame('CENTRO', $attributes['neighborhood']);
        $this->assertSame('1990-05-08', $attributes['birth_date']);
        $this->assertSame('NAYARIT', $attributes['birth_state']);
        $this->assertSame('TEPIC', $attributes['birth_place']);
    }

    public function test_maps_the_registry_procedure_and_person_details(): void
    {
        $attributes = $this->map([
            'Inicio' => 'APLICACIÓN WEB - AUTORIDAD',
            'SoloBusqueda' => 'SI',
            'archivomigracion' => 'SIN DATO',
            'Nacionalidad' => 'MEXICANA',
            'hablaespaniol' => 'SI',
            'TieneDiscapacidad' => 'NO',
            'TipoDiscapacidad' => 'SIN DATO',
            'edadanios' => 17,
            'edadmeses' => '1',
            'edaddias' => 3,
            'iddependenciaorigen' => 57,
            'PertenenciaPorCanalizacion' => 'FISCALIA GENERAL DE JALISCO|COMISION LOCAL DE BUSQUEDA|FISCALIA GENERAL DE JALISCO',
        ]);

        $this->assertSame('APLICACIÓN WEB - AUTORIDAD', $attributes['origin']);
        $this->assertTrue($attributes['search_only']);
        $this->assertNull($attributes['migration_file']);
        $this->assertSame('MEXICANA', $attributes['nationality']);
        $this->assertTrue($attributes['speaks_spanish']);
        $this->assertFalse($attributes['has_disability']);
        $this->assertNull($attributes['disability_type']);
        $this->assertSame([17, 1, 3], [$attributes['registered_age_years'], $attributes['registered_age_months'], $attributes['registered_age_days']]);
        $this->assertSame(57, $attributes['source_authority_id']);
        $this->assertSame(['FISCALIA GENERAL DE JALISCO', 'COMISION LOCAL DE BUSQUEDA'], json_decode($attributes['referred_to'], true));
    }

    #[DataProvider('booleans')]
    public function test_reads_yes_no_answers(mixed $value, ?bool $expected): void
    {
        $this->assertSame($expected, $this->map(['hablaespaniol' => $value])['speaks_spanish']);
    }

    /**
     * @return array<string, array{mixed, bool|null}>
     */
    public static function booleans(): array
    {
        return [
            'yes' => ['SI', true],
            'lowercase with spaces' => [' si ', true],
            'no' => ['NO', false],
            'asterisk' => ['*', null],
            'no data' => ['SIN DATO', null],
            'missing' => [null, null],
        ];
    }

    #[DataProvider('flexibleDates')]
    public function test_reads_dates_in_the_formats_of_the_registry(mixed $value, ?string $expected): void
    {
        $this->assertSame($expected, $this->map(['fechacaptura' => $value])['registered_date']);
    }

    /**
     * @return array<string, array{mixed, string|null}>
     */
    public static function flexibleDates(): array
    {
        return [
            'month first' => ['5/15/2024', '2024-05-15'],
            'day first when the day is above 12' => ['15/05/2024', '2024-05-15'],
            'ambiguous uses month first' => ['5/8/1990', '1990-05-08'],
            'iso' => ['2024-05-15T00:00:00.000Z', '2024-05-15'],
            'impossible day' => ['2/30/2024', null],
            'year too old' => ['1/1/1850', null],
            'garbage' => ['SIN DATO', null],
            'missing' => [null, null],
        ];
    }

    public function test_reads_the_date_of_the_notice_preferring_the_day_first_variant(): void
    {
        $this->assertSame('2026-06-15', $this->map(['ffechapercato' => '15/06/2026', 'fechapercato' => '6/15/2026'])['noticed_date']);
        $this->assertSame('2026-06-15', $this->map(['fechapercato' => '6/15/2026'])['noticed_date']);
    }

    public function test_reads_the_last_update_of_the_registry_with_its_time(): void
    {
        $this->assertSame('2026-09-28 19:15:21', $this->map(['fechaAct' => '28/9/2026, 19:15:21'])['source_updated_at']);
        $this->assertNull($this->map(['fechaAct' => 'ayer'])['source_updated_at']);
    }

    public function test_discards_impossible_registered_ages(): void
    {
        $attributes = $this->map(['edadanios' => 500, 'edadmeses' => 13, 'edaddias' => 40]);

        $this->assertNull($attributes['registered_age_years']);
        $this->assertNull($attributes['registered_age_months']);
        $this->assertNull($attributes['registered_age_days']);
    }

    #[DataProvider('registryPublishValues')]
    public function test_keeps_what_the_registry_says_about_publishing(mixed $value, ?string $expected): void
    {
        $this->assertSame($expected, $this->map(['PublicarFicha' => $value])['registry_publish']);
    }

    /**
     * @return array<string, array{mixed, string|null}>
     */
    public static function registryPublishValues(): array
    {
        return [
            'yes' => ['SI', 'SI'],
            'yes lowercase with spaces' => [' si ', 'SI'],
            'no' => ['NO', 'NO'],
            'no data' => ['SIN DATO', 'SIN DATO'],
            'something else' => ['*', 'SIN DATO'],
            'empty' => ['', null],
            'missing' => [null, null],
        ];
    }
}
