<?php

namespace App\Http\Requests;

use App\Enums\AgeRange;
use App\Enums\MexicanState;
use App\Enums\RecordType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPersonRecordsRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', Rule::enum(MexicanState::class)],
            'age' => ['nullable', Rule::enum(AgeRange::class)],
            'type' => ['nullable', Rule::enum(RecordType::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function searchTerm(): ?string
    {
        return $this->validated('q');
    }

    public function state(): ?MexicanState
    {
        return $this->enum('state', MexicanState::class);
    }

    public function ageRange(): ?AgeRange
    {
        return $this->enum('age', AgeRange::class);
    }

    public function recordType(): ?RecordType
    {
        return $this->enum('type', RecordType::class);
    }

    /**
     * Filtros activos tal como los necesita la interfaz.
     *
     * @return array{q: string|null, state: string|null, age: string|null, type: string|null}
     */
    public function filters(): array
    {
        return [
            'q' => $this->searchTerm(),
            'state' => $this->state()?->value,
            'age' => $this->ageRange()?->value,
            'type' => $this->recordType()?->value,
        ];
    }
}
