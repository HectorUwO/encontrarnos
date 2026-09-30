<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AgeRange: string
{
    use HasOptions;

    case Minor = 'under-18';
    case YoungAdult = '18-29';
    case Adult = '30-39';
    case Mature = '40-plus';

    public function label(): string
    {
        return match ($this) {
            self::Minor => 'Menos de 18 años',
            self::YoungAdult => '18 a 29 años',
            self::Adult => '30 a 39 años',
            self::Mature => '40 años o más',
        };
    }

    /**
     * Límites inclusivos del rango; `null` en el máximo indica que no tiene tope.
     *
     * @return array{0: int, 1: int|null}
     */
    public function bounds(): array
    {
        return match ($this) {
            self::Minor => [0, 17],
            self::YoungAdult => [18, 29],
            self::Adult => [30, 39],
            self::Mature => [40, null],
        };
    }

    public static function fromAge(?int $age): ?self
    {
        if ($age === null) {
            return null;
        }

        foreach (self::cases() as $range) {
            [$minimum, $maximum] = $range->bounds();

            if ($age >= $minimum && ($maximum === null || $age <= $maximum)) {
                return $range;
            }
        }

        return null;
    }
}
