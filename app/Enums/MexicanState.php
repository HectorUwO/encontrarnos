<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum MexicanState: string
{
    case Aguascalientes = 'aguascalientes';
    case BajaCalifornia = 'baja-california';
    case BajaCaliforniaSur = 'baja-california-sur';
    case Campeche = 'campeche';
    case Chiapas = 'chiapas';
    case Chihuahua = 'chihuahua';
    case CiudadDeMexico = 'ciudad-de-mexico';
    case Coahuila = 'coahuila';
    case Colima = 'colima';
    case Durango = 'durango';
    case Guanajuato = 'guanajuato';
    case Guerrero = 'guerrero';
    case Hidalgo = 'hidalgo';
    case Jalisco = 'jalisco';
    case Mexico = 'estado-de-mexico';
    case Michoacan = 'michoacan';
    case Morelos = 'morelos';
    case Nayarit = 'nayarit';
    case NuevoLeon = 'nuevo-leon';
    case Oaxaca = 'oaxaca';
    case Puebla = 'puebla';
    case Queretaro = 'queretaro';
    case QuintanaRoo = 'quintana-roo';
    case SanLuisPotosi = 'san-luis-potosi';
    case Sinaloa = 'sinaloa';
    case Sonora = 'sonora';
    case Tabasco = 'tabasco';
    case Tamaulipas = 'tamaulipas';
    case Tlaxcala = 'tlaxcala';
    case Veracruz = 'veracruz';
    case Yucatan = 'yucatan';
    case Zacatecas = 'zacatecas';

    /**
     * Nombres alternativos con los que aparece cada entidad en otras fuentes,
     * ya normalizados (sin acentos y en mayúsculas).
     */
    private const SOURCE_ALIASES = [
        'COAHUILA DE ZARAGOZA' => self::Coahuila,
        'DISTRITO FEDERAL' => self::CiudadDeMexico,
        'MEXICO' => self::Mexico,
        'MICHOACAN DE OCAMPO' => self::Michoacan,
        'VERACRUZ DE IGNACIO DE LA LLAVE' => self::Veracruz,
    ];

    public function label(): string
    {
        return match ($this) {
            self::Aguascalientes => 'Aguascalientes',
            self::BajaCalifornia => 'Baja California',
            self::BajaCaliforniaSur => 'Baja California Sur',
            self::Campeche => 'Campeche',
            self::Chiapas => 'Chiapas',
            self::Chihuahua => 'Chihuahua',
            self::CiudadDeMexico => 'Ciudad de México',
            self::Coahuila => 'Coahuila',
            self::Colima => 'Colima',
            self::Durango => 'Durango',
            self::Guanajuato => 'Guanajuato',
            self::Guerrero => 'Guerrero',
            self::Hidalgo => 'Hidalgo',
            self::Jalisco => 'Jalisco',
            self::Mexico => 'Estado de México',
            self::Michoacan => 'Michoacán',
            self::Morelos => 'Morelos',
            self::Nayarit => 'Nayarit',
            self::NuevoLeon => 'Nuevo León',
            self::Oaxaca => 'Oaxaca',
            self::Puebla => 'Puebla',
            self::Queretaro => 'Querétaro',
            self::QuintanaRoo => 'Quintana Roo',
            self::SanLuisPotosi => 'San Luis Potosí',
            self::Sinaloa => 'Sinaloa',
            self::Sonora => 'Sonora',
            self::Tabasco => 'Tabasco',
            self::Tamaulipas => 'Tamaulipas',
            self::Tlaxcala => 'Tlaxcala',
            self::Veracruz => 'Veracruz',
            self::Yucatan => 'Yucatán',
            self::Zacatecas => 'Zacatecas',
        };
    }

    /**
     * Clave de la entidad en el catálogo del INEGI (1 a 32), que también usa el
     * registro nacional para identificarla.
     */
    public function code(): int
    {
        return match ($this) {
            self::Aguascalientes => 1,
            self::BajaCalifornia => 2,
            self::BajaCaliforniaSur => 3,
            self::Campeche => 4,
            self::Coahuila => 5,
            self::Colima => 6,
            self::Chiapas => 7,
            self::Chihuahua => 8,
            self::CiudadDeMexico => 9,
            self::Durango => 10,
            self::Guanajuato => 11,
            self::Guerrero => 12,
            self::Hidalgo => 13,
            self::Jalisco => 14,
            self::Mexico => 15,
            self::Michoacan => 16,
            self::Morelos => 17,
            self::Nayarit => 18,
            self::NuevoLeon => 19,
            self::Oaxaca => 20,
            self::Puebla => 21,
            self::Queretaro => 22,
            self::QuintanaRoo => 23,
            self::SanLuisPotosi => 24,
            self::Sinaloa => 25,
            self::Sonora => 26,
            self::Tabasco => 27,
            self::Tamaulipas => 28,
            self::Tlaxcala => 29,
            self::Veracruz => 30,
            self::Yucatan => 31,
            self::Zacatecas => 32,
        };
    }

    /**
     * Población de la entidad según el Censo de Población y Vivienda 2020 del
     * INEGI; sirve para calcular tasas por cada 100 mil habitantes.
     */
    public function population(): int
    {
        return match ($this) {
            self::Aguascalientes => 1_425_607,
            self::BajaCalifornia => 3_769_020,
            self::BajaCaliforniaSur => 798_447,
            self::Campeche => 928_363,
            self::Coahuila => 3_146_771,
            self::Colima => 731_391,
            self::Chiapas => 5_543_828,
            self::Chihuahua => 3_741_869,
            self::CiudadDeMexico => 9_209_944,
            self::Durango => 1_832_650,
            self::Guanajuato => 6_166_934,
            self::Guerrero => 3_540_685,
            self::Hidalgo => 3_082_841,
            self::Jalisco => 8_348_151,
            self::Mexico => 16_992_418,
            self::Michoacan => 4_748_846,
            self::Morelos => 1_971_520,
            self::Nayarit => 1_235_456,
            self::NuevoLeon => 5_784_442,
            self::Oaxaca => 4_132_148,
            self::Puebla => 6_583_278,
            self::Queretaro => 2_368_467,
            self::QuintanaRoo => 1_857_985,
            self::SanLuisPotosi => 2_822_255,
            self::Sinaloa => 3_026_943,
            self::Sonora => 2_944_840,
            self::Tabasco => 2_402_598,
            self::Tamaulipas => 3_527_735,
            self::Tlaxcala => 1_342_977,
            self::Veracruz => 8_062_579,
            self::Yucatan => 2_320_898,
            self::Zacatecas => 1_622_138,
        };
    }

    /**
     * Devuelve `null` para claves que no son una entidad, como la 33 («se
     * desconoce») del registro nacional.
     */
    public static function fromCode(int $code): ?self
    {
        return collect(self::cases())->first(fn (self $state): bool => $state->code() === $code);
    }

    /**
     * Interpreta el nombre de la entidad tal como lo publica el registro
     * nacional (mayúsculas, sin acentos y a veces con espacios sobrantes).
     * Devuelve `null` para valores como «SE DESCONOCE».
     */
    public static function fromSource(?string $name): ?self
    {
        static $byNormalizedLabel = null;

        $byNormalizedLabel ??= collect(self::cases())->mapWithKeys(
            fn (self $state): array => [self::normalize($state->label()) => $state],
        )->all();

        $normalized = self::normalize((string) $name);

        return self::SOURCE_ALIASES[$normalized] ?? $byNormalizedLabel[$normalized] ?? null;
    }

    /**
     * Opciones ordenadas alfabéticamente para los filtros de la interfaz.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $state): array => ['value' => $state->value, 'label' => $state->label()])
            ->sortBy(fn (array $option): string => Str::ascii($option['label']))
            ->values()
            ->all();
    }

    private static function normalize(string $name): string
    {
        return Str::of($name)->squish()->ascii()->upper()->toString();
    }
}
