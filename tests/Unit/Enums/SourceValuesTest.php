<?php

namespace Tests\Unit\Enums;

use App\Enums\DisappearanceStatus;
use App\Enums\Sex;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SourceValuesTest extends TestCase
{
    #[DataProvider('sexValues')]
    public function test_interprets_the_sex_used_by_the_national_registry(?string $value, Sex $expected): void
    {
        $this->assertSame($expected, Sex::fromSource($value));
    }

    /**
     * @return array<string, array{string|null, Sex}>
     */
    public static function sexValues(): array
    {
        return [
            'woman' => ['MUJER', Sex::Female],
            'man' => ['HOMBRE', Sex::Male],
            'lowercase with spaces' => [' hombre ', Sex::Male],
            'undetermined' => ['INDETERMINADO', Sex::Unknown],
            'missing' => [null, Sex::Unknown],
        ];
    }

    #[DataProvider('statusValues')]
    public function test_interprets_the_status_used_by_the_national_registry(?string $value, ?DisappearanceStatus $expected): void
    {
        $this->assertSame($expected, DisappearanceStatus::fromSource($value));
    }

    /**
     * @return array<string, array{string|null, DisappearanceStatus|null}>
     */
    public static function statusValues(): array
    {
        return [
            'disappeared' => ['DESAPARECIDA', DisappearanceStatus::Disappeared],
            'not located' => ['NO LOCALIZADA', DisappearanceStatus::NotLocated],
            'no data' => ['SIN DATO', null],
            'missing' => [null, null],
        ];
    }
}
