<?php

namespace App\Http\Requests;

use App\Enums\PersonRequestType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(PersonRequestType::class)],
            'name' => ['nullable', 'string', 'max:120'],
            'place' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:3000'],
            'contact_email' => ['required', 'string', 'email', 'max:254'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Elige el tipo de solicitud.',
            'type.enum' => 'El tipo de solicitud no es válido.',
            'name.max' => 'El nombre no puede tener más de :max caracteres.',
            'place.required' => 'Indica el lugar (municipio y estado).',
            'place.max' => 'El lugar no puede tener más de :max caracteres.',
            'description.required' => 'Agrega una descripción.',
            'description.max' => 'La descripción no puede tener más de :max caracteres.',
            'contact_email.required' => 'Indica un correo de contacto.',
            'contact_email.email' => 'Escribe un correo electrónico válido.',
            'contact_email.max' => 'El correo no puede tener más de :max caracteres.',
            'photo.image' => 'Elige una imagen JPG, PNG o WEBP.',
            'photo.mimes' => 'Elige una imagen JPG, PNG o WEBP.',
            'photo.max' => 'La fotografía debe pesar menos de 10 MB.',
            'photo.uploaded' => 'No se pudo subir la fotografía. Intenta de nuevo.',
        ];
    }
}
