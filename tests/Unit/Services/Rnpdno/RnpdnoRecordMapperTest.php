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

    public function test_ignores_addresses_and_birth_data(): void
    {
        $attributes = $this->map([
            'nombre' => 'MARIA',
            'calle' => 'CALLE SECRETA',
            'fechanacimiento' => '01/02/1990',
            'lugarnacimiento' => 'LUGAR SECRETO',
            'nombreasentamiento' => 'COLONIA SECRETA',
        ]);

        $this->assertStringNotContainsString('SECRET', json_encode($attributes));
        $this->assertStringNotContainsString('1990', json_encode($attributes));
    }
}
