<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum Sex: string
{
    case Female = 'female';
    case Male = 'male';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Female => 'Mujer',
            self::Male => 'Hombre',
            self::Unknown => 'No especificado',
        };
    }

    /**
     * Interpreta el sexo tal como lo publica el registro nacional.
     */
    public static function fromSource(?string $sex): self
    {
        return match (Str::of((string) $sex)->squish()->ascii()->upper()->toString()) {
            'MUJER' => self::Female,
            'HOMBRE' => self::Male,
            default => self::Unknown,
        };
    }
}
