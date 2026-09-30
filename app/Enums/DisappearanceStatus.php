<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum DisappearanceStatus: string
{
    case Disappeared = 'disappeared';
    case NotLocated = 'not_located';

    public function label(): string
    {
        return match ($this) {
            self::Disappeared => 'Desaparecida',
            self::NotLocated => 'No localizada',
        };
    }

    /**
     * Interpreta el estatus tal como lo publica el registro nacional.
     */
    public static function fromSource(?string $status): ?self
    {
        return match (Str::of((string) $status)->squish()->ascii()->upper()->toString()) {
            'DESAPARECIDA' => self::Disappeared,
            'NO LOCALIZADA' => self::NotLocated,
            default => null,
        };
    }
}
