<?php

namespace App\Http\Requests;

use Illuminate\Support\Arr;

/**
 * Filtros de la página de un estado: el estado viene en la ruta, así que solo
 * queda el periodo.
 */
class ShowStateStatisticsRequest extends ShowStatisticsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return Arr::except(parent::rules(), 'state');
    }

    /**
     * Con un periodo inválido se vuelve a la página del estado sin filtros.
     */
    protected function getRedirectUrl(): string
    {
        return route('statistics.state', ['state' => $this->route('state')]);
    }
}
