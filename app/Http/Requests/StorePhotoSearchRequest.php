<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePhotoSearchRequest extends FormRequest
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
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'Selecciona una fotografía.',
            'photo.image' => 'Selecciona una imagen JPG, PNG o WEBP.',
            'photo.mimes' => 'Selecciona una imagen JPG, PNG o WEBP.',
            'photo.max' => 'La imagen debe pesar menos de 10 MB.',
            'photo.uploaded' => 'No se pudo subir la imagen. Intenta de nuevo.',
        ];
    }
}
