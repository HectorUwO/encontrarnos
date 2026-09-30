<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OfferInformationRequest extends FormRequest
{
    /**
     * Solo las cuentas con correo verificado pueden escribir a un contacto.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasVerifiedEmail();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 +()\-.]{7,30}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Escribe la información que quieres compartir.',
            'message.min' => 'Cuéntanos un poco más (al menos :min caracteres).',
            'message.max' => 'El mensaje no puede tener más de :max caracteres.',
            'phone.regex' => 'Escribe un teléfono válido.',
        ];
    }
}
