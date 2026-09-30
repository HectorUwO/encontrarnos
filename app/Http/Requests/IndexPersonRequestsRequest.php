<?php

namespace App\Http\Requests;

use App\Enums\AgeRange;
use App\Enums\MexicanState;
use App\Enums\PersonRequestType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPersonRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', Rule::enum(MexicanState::class)],
            'age' => ['nullable', Rule::enum(AgeRange::class)],
            'type' => ['nullable', Rule::enum(PersonRequestType::class)],
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

    public function requestType(): ?PersonRequestType
    {
        return $this->enum('type', PersonRequestType::class);
    }

    /**
     * @return array{q: string|null, state: string|null, age: string|null, type: string|null}
     */
    public function filters(): array
    {
        return [
            'q' => $this->searchTerm(),
            'state' => $this->state()?->value,
            'age' => $this->ageRange()?->value,
            'type' => $this->requestType()?->value,
        ];
    }
}
