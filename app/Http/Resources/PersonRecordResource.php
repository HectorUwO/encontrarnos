<?php

namespace App\Http\Resources;

use App\Enums\PhotoSize;
use App\Enums\Sex;
use App\Models\PersonRecord;
use App\Services\Photos\PhotoCache;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Lista blanca de los campos públicos de una ficha: lo que no aparece aquí
 * (los identificadores del registro de origen, por ejemplo) nunca sale de la
 * aplicación. Para publicar más información, agrégala en este único lugar.
 *
 * @mixin PersonRecord
 */
class PersonRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'folio' => $this->folio,
            'name' => $this->name ?? 'Ficha '.$this->folio,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'status_label' => $this->disappearance_status?->label(),
            'sex' => $this->sex?->value,
            'age' => $this->age,
            'current_age' => $this->current_age,
            'state' => $this->state?->value,
            'state_label' => $this->state?->label(),
            'municipality' => $this->municipality,
            'event_date' => $this->event_date?->toDateString(),
            'event_date_label' => $this->event_date?->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
            'description' => $this->description,
            'traits' => collect($this->orderedTraits())
                ->map(fn (string $value, string $key): array => [
                    'label' => PersonRecord::traitLabel($key),
                    'value' => Str::lower($value),
                ])
                ->values()
                ->all(),
            'clothing' => $this->sentenceCase($this->clothing),
            'distinguishing_marks' => $this->sentenceCase($this->distinguishing_marks),
            'authority' => $this->authority,
            'has_photo' => $this->hasPhoto(),
            'portrait' => $this->portraitUrl(PhotoSize::Thumbnail),
            'portrait_large' => $this->portraitUrl(PhotoSize::Medium),
        ];
    }

    /**
     * Fotografía a ese tamaño o, si la ficha no tiene, una silueta.
     */
    private function portraitUrl(PhotoSize $size): string
    {
        return $this->hasPhoto()
            ? route('records.photo', [
                'personRecord' => $this->resource,
                'size' => $size->value,
                'v' => PhotoCache::version($this->photo_path),
            ], absolute: false)
            : $this->placeholder();
    }

    private function sentenceCase(?string $text): ?string
    {
        return $text === null ? null : Str::ucfirst(Str::lower($text));
    }

    private function placeholder(): string
    {
        return $this->sex === Sex::Female ? '/placeholder-woman.webp' : '/placeholder-man.webp';
    }
}
