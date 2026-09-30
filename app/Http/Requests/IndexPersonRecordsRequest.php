<?php

namespace App\Http\Requests;

use App\Enums\AgeRange;
use App\Enums\DisappearanceStatus;
use App\Enums\MexicanState;
use App\Enums\Sex;
use App\Services\Search\RecordQuery;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
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
            'age_from' => ['nullable', 'integer', 'min:0', 'max:110'],
            'age_to' => ['nullable', 'integer', 'min:0', 'max:110', Rule::when($this->filled('age_from'), 'gte:age_from')],
            'sex' => ['nullable', Rule::enum(Sex::class)],
            'status' => ['nullable', Rule::enum(DisappearanceStatus::class)],
            'photo' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), 'after_or_equal:from')],
            'municipality' => ['nullable', 'string', 'max:80'],
            'authority' => ['nullable', 'string', 'max:120'],
            'nationality' => ['nullable', 'string', 'max:80'],
            'disability' => ['nullable', 'boolean'],
            'registry' => ['nullable', Rule::in(['SI', 'NO', 'SIN DATO'])],
            'sort' => ['nullable', Rule::in(RecordQuery::SORTS)],
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

    /**
     * Solo los administradores pueden filtrar por lo que el registro dijo
     * sobre publicar la ficha; para los demás el filtro no existe.
     */
    public function registry(): ?string
    {
        return Gate::allows('view-registry-publication') ? $this->validated('registry') : null;
    }

    public function recordQuery(): RecordQuery
    {
        $text = fn (string $key): ?string => filled($this->validated($key)) ? trim((string) $this->validated($key)) : null;
        $number = fn (string $key): ?int => $this->validated($key) === null ? null : (int) $this->validated($key);

        return new RecordQuery(
            term: $this->searchTerm(),
            state: $this->state(),
            ageRange: $this->ageRange(),
            ageFrom: $number('age_from'),
            ageTo: $number('age_to'),
            sex: $this->enum('sex', Sex::class),
            status: $this->enum('status', DisappearanceStatus::class),
            withPhoto: $this->boolean('photo'),
            from: $text('from'),
            to: $text('to'),
            municipality: $text('municipality'),
            authority: $text('authority'),
            nationality: $text('nationality'),
            withDisability: $this->boolean('disability'),
            registryPublish: $this->registry(),
            sort: $this->validated('sort') ?? 'recent',
        );
    }

    /**
     * Filtros activos tal como los necesita la interfaz.
     *
     * @return array<string, string|int|bool|null>
     */
    public function filters(): array
    {
        $query = $this->recordQuery();

        return [
            'q' => $query->term,
            'state' => $query->state?->value,
            'age' => $query->ageRange?->value,
            'age_from' => $query->ageFrom,
            'age_to' => $query->ageTo,
            'sex' => $query->sex?->value,
            'status' => $query->status?->value,
            'photo' => $query->withPhoto ? true : null,
            'from' => $query->from,
            'to' => $query->to,
            'municipality' => $query->municipality,
            'authority' => $query->authority,
            'nationality' => $query->nationality,
            'disability' => $query->withDisability ? true : null,
            'registry' => $query->registryPublish,
            'sort' => $query->sort === 'recent' ? null : $query->sort,
        ];
    }
}
