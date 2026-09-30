<?php

namespace App\Enums;

/**
 * Tamaños reducidos de las fotografías de las fichas. Las originales pesan
 * 180 KB en promedio (hasta más de 3 MB), demasiado para una lista de fichas.
 */
enum PhotoSize: string
{
    case Thumbnail = 'thumb';
    case Medium = 'medium';

    /**
     * Ancho máximo en píxeles; el alto guarda la proporción.
     */
    public function width(): int
    {
        return match ($this) {
            self::Thumbnail => 320,
            self::Medium => 800,
        };
    }
}
