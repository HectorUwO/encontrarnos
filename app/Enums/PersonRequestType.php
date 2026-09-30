<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PersonRequestType: string
{
    use HasOptions;

    case Search = 'search';
    case Identification = 'identification';

    public function label(): string
    {
        return match ($this) {
            self::Search => 'Búsqueda de una persona',
            self::Identification => 'Identificación de una persona',
        };
    }
}
