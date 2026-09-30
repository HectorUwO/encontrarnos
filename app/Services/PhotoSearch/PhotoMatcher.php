<?php

namespace App\Services\PhotoSearch;

use App\Models\PersonRecord;
use App\Models\PersonRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * Motor que compara una fotografía con las fichas publicadas.
 *
 * Solo devuelve fichas publicadas y solicitudes aprobadas que siguen abiertas.
 */
interface PhotoMatcher
{
    public function isAvailable(): bool;

    /**
     * Fichas publicadas que se parecen a la fotografía, de la más a la menos
     * probable.
     *
     * @return Collection<int, PersonRecord|PersonRequest>
     */
    public function match(UploadedFile $photo): Collection;
}
