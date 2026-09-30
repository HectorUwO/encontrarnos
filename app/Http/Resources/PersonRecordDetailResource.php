<?php

namespace App\Http\Resources;

use App\Models\PersonRecord;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Todo lo que se guarda de una ficha, para su página. Los identificadores del
 * registro de origen nunca salen. Los datos personales (nacimiento y domicilio)
 * solo van en el bloque `sensitive`, y únicamente cuando la habilidad
 * `view-sensitive-record-data` lo permite.
 *
 * @mixin PersonRecord
 */
class PersonRecordDetailResource extends PersonRecordResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canSeeSensitive = Gate::allows('view-sensitive-record-data');

        return [
            ...parent::toArray($request),
            'registry_publish' => $this->when(Gate::allows('view-registry-publication'), fn () => $this->registry_publish),
            'noticed_date_label' => $this->label($this->noticed_date),
            'registered_date_label' => $this->label($this->registered_date),
            'source_updated_at_label' => $this->source_updated_at?->locale('es')->isoFormat('D [de] MMMM [de] YYYY, HH:mm'),
            'origin' => $this->origin,
            'search_only' => $this->search_only,
            'referred_to' => $this->referred_to ?? [],
            'migration_file' => $this->migration_file,
            'registered_age' => [
                'years' => $this->registered_age_years,
                'months' => $this->registered_age_months,
                'days' => $this->registered_age_days,
            ],
            'nationality' => $this->nationality,
            'speaks_spanish' => $this->speaks_spanish,
            'has_disability' => $this->has_disability,
            'disability_type' => $this->disability_type,
            'sensitive_restricted' => ! $canSeeSensitive,
            'sensitive' => $canSeeSensitive ? [
                'birth_date_label' => $this->label($this->birth_date),
                'birth_state' => $this->birth_state,
                'birth_place' => $this->birth_place,
                'street' => $this->street,
                'exterior_number' => $this->exterior_number,
                'interior_number' => $this->interior_number,
                'postal_code' => $this->postal_code,
                'neighborhood' => $this->neighborhood,
            ] : null,
        ];
    }

    private function label(?CarbonInterface $date): ?string
    {
        return $date?->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
    }
}
