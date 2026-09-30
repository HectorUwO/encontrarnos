<?php

namespace App\Http\Resources;

use App\Enums\PersonRequestStatus;
use App\Enums\PhotoSize;
use App\Models\PersonRequest;
use App\Services\Photos\PhotoCache;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Datos públicos de una solicitud. El correo de contacto nunca se incluye.
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
            'place' => $this->place,
            'description' => $this->description,
            'photo' => $this->photoUrl(PhotoSize::Medium),
            'photo_thumb' => $this->photoUrl(PhotoSize::Thumbnail),
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
