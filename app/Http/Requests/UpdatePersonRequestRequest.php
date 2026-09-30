<?php

namespace App\Http\Requests;

use App\Models\PersonRequest;

class UpdatePersonRequestRequest extends StorePersonRequestRequest
{
    /**
     * Solo quien creó la solicitud (o la envió con su mismo correo) puede editarla.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && PersonRequest::query()->ownedBy($user)->whereKey($this->route('personRequest'))->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }
}
