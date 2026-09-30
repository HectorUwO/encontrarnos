<?php

namespace App\Http\Requests;

use App\Enums\MexicanState;
use App\Enums\PersonRequestType;
use App\Enums\Sex;
use App\Models\PersonRecord;
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
            'name' => [
                Rule::requiredIf(fn (): bool => $this->input('type') === PersonRequestType::Search->value),
                'nullable', 'string', 'max:120',
            ],
            'sex' => ['nullable', Rule::enum(Sex::class)],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'state' => ['required', Rule::enum(MexicanState::class)],
            'municipality' => ['required', 'string', 'max:120'],
            'event_date' => ['nullable', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:3000'],
            'traits' => ['nullable', 'array'],
            'traits.*' => ['nullable', 'string', 'max:80'],
            'clothing' => ['nullable', 'string', 'max:1000'],
            'distinguishing_marks' => ['nullable', 'string', 'max:1000'],
            'institution' => ['nullable', 'string', 'max:150'],
            'contact_email' => ['required', 'string', 'email', 'max:254'],
            'contact_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 +()\-.]{7,30}$/'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    /**
     * Solo se guardan los rasgos conocidos y con valor.
     *
     * @return array<string, string>
     */
    public function traits(): array
    {
        return collect($this->input('traits', []))
            ->only(PersonRecord::traitKeys())
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Elige el tipo de solicitud.',
            'type.enum' => 'El tipo de solicitud no es válido.',
            'name.required' => 'Escribe el nombre de la persona que buscas.',
            'name.max' => 'El nombre no puede tener más de :max caracteres.',
            'sex.enum' => 'El sexo no es válido.',
            'age.integer' => 'La edad debe ser un número.',
            'age.min' => 'La edad no puede ser negativa.',
            'age.max' => 'La edad no puede ser mayor de :max años.',
            'state.required' => 'Elige el estado.',
            'state.enum' => 'El estado no es válido.',
            'municipality.required' => 'Indica el municipio.',
            'municipality.max' => 'El municipio no puede tener más de :max caracteres.',
            'event_date.date' => 'Escribe una fecha válida.',
            'event_date.before_or_equal' => 'La fecha no puede ser futura.',
            'description.required' => 'Agrega una descripción.',
            'description.max' => 'La descripción no puede tener más de :max caracteres.',
            'traits.*.max' => 'Cada rasgo puede tener hasta :max caracteres.',
            'clothing.max' => 'Las prendas no pueden tener más de :max caracteres.',
            'distinguishing_marks.max' => 'Las señas no pueden tener más de :max caracteres.',
            'institution.max' => 'El nombre de la institución no puede tener más de :max caracteres.',
            'contact_email.required' => 'Indica un correo de contacto.',
            'contact_email.email' => 'Escribe un correo electrónico válido.',
            'contact_email.max' => 'El correo no puede tener más de :max caracteres.',
            'contact_phone.regex' => 'Escribe un teléfono válido.',
            'photo.image' => 'Elige una imagen JPG, PNG o WEBP.',
            'photo.mimes' => 'Elige una imagen JPG, PNG o WEBP.',
            'photo.max' => 'La fotografía debe pesar menos de 10 MB.',
            'photo.uploaded' => 'No se pudo subir la fotografía. Intenta de nuevo.',
        ];
    }
}
