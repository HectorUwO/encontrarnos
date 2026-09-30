<?php

namespace App\Http\Resources;

use App\Enums\PersonRequestStatus;
use App\Enums\PhotoSize;
use App\Models\PersonRecord;
use App\Models\PersonRequest;
use App\Services\Photos\PhotoCache;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Datos públicos de una solicitud. El correo y el teléfono de contacto nunca
 * se incluyen: quien quiera ayudar escribe desde la ficha y el mensaje se
 * reenvía sin revelar el contacto.
 *
 * @mixin PersonRequest
 */
class PersonRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference(),
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'name' => $this->name,
            'sex' => $this->sex?->value,
            'sex_label' => $this->sex?->label(),
            'age' => $this->age,
            'state' => $this->state?->value,
            'state_label' => $this->state?->label(),
            'municipality' => $this->municipality,
            'place' => $this->placeLabel(),
            'event_date_label' => $this->event_date?->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
            'description' => $this->description,
            'traits' => collect($this->orderedTraits())
                ->map(fn (string $value, string $key): array => [
                    'label' => PersonRecord::traitLabel($key),
                    'value' => Str::lower($value),
                ])
                ->values()
                ->all(),
            'clothing' => $this->clothing,
            'distinguishing_marks' => $this->distinguishing_marks,
            'institution' => $this->institution,
            'closed' => $this->isClosed(),
            'closed_reason' => $this->closed_reason,
            'closed_at_label' => $this->closed_at?->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
            'has_photo' => $this->hasPhoto() && $this->status === PersonRequestStatus::Approved,
            'photo' => $this->photoUrl(PhotoSize::Medium),
            'photo_thumb' => $this->photoUrl(PhotoSize::Thumbnail),
            'url' => route('requests.show', $this->resource, absolute: false),
            'created_at_label' => $this->created_at->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
        ];
    }

    /**
     * Solo las solicitudes aprobadas muestran su fotografía.
     */
    private function photoUrl(PhotoSize $size): ?string
    {
        return $this->hasPhoto() && $this->status === PersonRequestStatus::Approved
            ? route('requests.photo', [
                'personRequest' => $this->resource,
                'size' => $size->value,
                'v' => PhotoCache::version($this->photo_path),
            ], absolute: false)
            : null;
    }
}
