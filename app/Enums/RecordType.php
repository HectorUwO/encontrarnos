<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RecordType: string
{
    use HasOptions;

    case MissingPerson = 'missing_person';
    case IdentificationRequest = 'identification_request';

    public function label(): string
    {
        return match ($this) {
            self::MissingPerson => 'Persona desaparecida',
            self::IdentificationRequest => 'Solicitud de identificación',
        };
    }
}
