<?php

namespace App\Enums;

enum PersonRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente de revisión',
            self::Approved => 'Aprobada',
            self::Rejected => 'Rechazada',
        };
    }
}
