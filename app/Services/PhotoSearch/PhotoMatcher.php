<?php

namespace App\Services\PhotoSearch;

use App\Models\PersonRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * Motor que compara una fotografía con las fichas publicadas.
 *
 * La comparación de rostros es un tratamiento de datos biométricos: hasta que
 * se decida qué motor usar, la aplicación funciona con NullPhotoMatcher.
 */
interface PhotoMatcher
{
    public function isAvailable(): bool;

    /**
     * Fichas publicadas que se parecen a la fotografía, de la más a la menos
     * probable.
     *
     * @return Collection<int, PersonRecord>
     */
    public function match(UploadedFile $photo): Collection;
}
