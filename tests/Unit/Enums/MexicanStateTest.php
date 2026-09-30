<?php

namespace Tests\Unit\Enums;

use App\Enums\MexicanState;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MexicanStateTest extends TestCase
{
    #[DataProvider('sourceNames')]
    public function test_interprets_the_name_used_by_the_national_registry(?string $name, ?MexicanState $expected): void
    {
        $this->assertSame($expected, MexicanState::fromSource($name));
    }

    /**
     * @return array<string, array{string|null, MexicanState|null}>
     */
    public static function sourceNames(): array
    {
        return [
            'state of Mexico' => ['ESTADO DE MEXICO', MexicanState::Mexico],
            'trailing space' => ['MICHOACAN ', MexicanState::Michoacan],
            'accents and lowercase' => ['nuevo león', MexicanState::NuevoLeon],
            'long official name' => ['COAHUILA DE ZARAGOZA', MexicanState::Coahuila],
            'former name of Mexico City' => ['DISTRITO FEDERAL', MexicanState::CiudadDeMexico],
            'unknown' => ['SE DESCONOCE', null],
            'blank' => ['', null],
            'missing' => [null, null],
        ];
    }

    public function test_offers_every_state_sorted_alphabetically_ignoring_accents(): void
    {
        $options = MexicanState::options();

        $this->assertCount(32, $options);
        $this->assertSame(['value' => 'aguascalientes', 'label' => 'Aguascalientes'], $options[0]);
        $this->assertSame(
            ['Chiapas', 'Chihuahua', 'Ciudad de México'],
            array_column(array_slice($options, 4, 3), 'label'),
        );
    }

    public function test_each_state_has_its_own_inegi_code_from_1_to_32(): void
    {
        $codes = array_map(fn (MexicanState $state): int => $state->code(), MexicanState::cases());

        $this->assertSame(range(1, 32), collect($codes)->sort()->values()->all());
    }

    public function test_finds_a_state_by_its_inegi_code(): void
    {
        $this->assertSame(MexicanState::Aguascalientes, MexicanState::fromCode(1));
        $this->assertSame(MexicanState::CiudadDeMexico, MexicanState::fromCode(9));
        $this->assertSame(MexicanState::Mexico, MexicanState::fromCode(15));
        $this->assertSame(MexicanState::Zacatecas, MexicanState::fromCode(32));
    }

    public function test_the_unknown_code_of_the_registry_is_not_a_state(): void
    {
        $this->assertNull(MexicanState::fromCode(33));
        $this->assertNull(MexicanState::fromCode(0));
    }

    public function test_state_populations_add_up_to_the_2020_census_total(): void
    {
        $total = array_sum(array_map(fn (MexicanState $state): int => $state->population(), MexicanState::cases()));

        $this->assertSame(126_014_024, $total);
    }
}
