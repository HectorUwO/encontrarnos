<?php

namespace App\Http\Requests;

use App\Enums\MexicanState;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowStatisticsRequest extends FormRequest
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
            'state' => ['nullable', Rule::enum(MexicanState::class)],
            'from' => ['nullable', 'integer', 'between:1900,2100'],
            'to' => ['nullable', 'integer', 'between:1900,2100', Rule::when($this->filled('from'), ['gte:from'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'state.enum' => 'El estado no es válido.',
            'from.integer' => 'El año inicial debe ser un número.',
            'from.between' => 'El año inicial debe estar entre :min y :max.',
            'to.integer' => 'El año final debe ser un número.',
            'to.between' => 'El año final debe estar entre :min y :max.',
            'to.gte' => 'El año final no puede ser anterior al inicial.',
        ];
    }

    public function state(): ?MexicanState
    {
        return $this->enum('state', MexicanState::class);
    }

    public function fromYear(): ?int
    {
        return $this->filled('from') ? $this->integer('from') : null;
    }

    public function toYear(): ?int
    {
        return $this->filled('to') ? $this->integer('to') : null;
    }
}
