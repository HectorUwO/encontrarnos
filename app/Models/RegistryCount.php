<?php

namespace App\Models;

use App\Enums\AgeRange;
use App\Enums\DisappearanceStatus;
use App\Enums\MexicanState;
use App\Enums\Sex;
use Database\Factories\RegistryCountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Conteos del registro nacional agrupados por estado, municipio, mes, sexo,
 * edad y estatus. No contiene datos de personas: solo cuántos registros
 * comparten esa combinación. Los registros confidenciales del registro no
 * informan fecha, sexo, edad ni estatus, por eso esas columnas quedan vacías.
 */
#[Fillable([
    'state',
    'municipality',
    'month',
    'sex',
    'age_range',
    'status',
    'confidential',
    'total',
])]
class RegistryCount extends Model
{
    /** @use HasFactory<RegistryCountFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => MexicanState::class,
            'month' => 'date',
            'sex' => Sex::class,
            'age_range' => AgeRange::class,
            'status' => DisappearanceStatus::class,
            'confidential' => 'boolean',
            'total' => 'integer',
        ];
    }

    #[Scope]
    protected function inState(Builder $query, ?MexicanState $state): void
    {
        if ($state !== null) {
            $query->where('state', $state);
        }
    }

    /**
     * Acota a los años indicados (ambos incluidos). Solo los registros con
     * fecha conocida pertenecen a un periodo.
     */
    #[Scope]
    protected function withinYears(Builder $query, ?int $from, ?int $to): void
    {
        if ($from === null && $to === null) {
            return;
        }

        $query->whereNotNull('month');

        if ($from !== null) {
            $query->where('month', '>=', sprintf('%04d-01-01', $from));
        }

        if ($to !== null) {
            $query->where('month', '<=', sprintf('%04d-12-31', $to));
        }
    }
}
