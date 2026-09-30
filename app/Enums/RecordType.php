<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RecordType: string
{
    use HasOptions;

    case MissingPerson = 'missing_person';

    public function label(): string
    {
        return match ($this) {
            self::MissingPerson => 'Persona desaparecida',
        };
    }
}
