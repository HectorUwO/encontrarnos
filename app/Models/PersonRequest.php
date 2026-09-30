<?php

namespace App\Models;

use App\Enums\AgeRange;
use App\Enums\MexicanState;
use App\Enums\PersonRequestStatus;
use App\Enums\PersonRequestType;
use App\Enums\Sex;
use Database\Factories\PersonRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'type',
    'status',
    'name',
    'sex',
    'age',
    'state',
    'municipality',
    'place',
    'event_date',
    'description',
    'traits',
    'clothing',
    'distinguishing_marks',
    'institution',
    'contact_email',
    'contact_phone',
    'photo_path',
    'closed_at',
    'closed_reason',
])]
#[Hidden(['contact_email', 'contact_phone'])]
class PersonRequest extends Model
{
    /** @use HasFactory<PersonRequestFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PersonRequestType::class,
            'status' => PersonRequestStatus::class,
            'sex' => Sex::class,
            'state' => MexicanState::class,
            'event_date' => 'date',
            'traits' => 'array',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function informationReports(): HasMany
    {
        return $this->hasMany(InformationReport::class);
    }

    /**
     * Solicitudes revisadas y visibles para el público.
     */
    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', PersonRequestStatus::Approved);
    }

    /**
     * Aprobadas y todavía abiertas: lo que se muestra en el catálogo y recibe información.
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', PersonRequestStatus::Approved)->whereNull('closed_at');
    }

    /**
     * Solicitudes de una persona: las creadas con su cuenta y las que envió sin
     * cuenta con el mismo correo de contacto, una vez verificado.
     */
    #[Scope]
    protected function ownedBy(Builder $query, User $user): void
    {
        $query->where(function (Builder $query) use ($user): void {
            $query->where('user_id', $user->id)
                ->when($user->hasVerifiedEmail(), fn (Builder $query) => $query
                    ->orWhere(fn (Builder $query) => $query->whereNull('user_id')->where('contact_email', $user->email)));
        });
    }

    /**
     * Cada palabra debe aparecer en la referencia, el nombre, el lugar, la
     * descripción o la institución; el orden de las palabras no importa.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $words = preg_split('/\s+/u', trim((string) $term), 6, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($words as $word) {
            $like = '%'.addcslashes($word, '\\%_').'%';
            $stateValues = $this->stateValuesMatching($word);
            $id = preg_match('/^(sol-?)?0*(\d+)$/i', $word, $match) === 1 ? (int) $match[2] : null;

            $query->where(function (Builder $query) use ($like, $stateValues, $id): void {
                $query->where('name', 'like', $like)
                    ->orWhere('municipality', 'like', $like)
                    ->orWhere('place', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('institution', 'like', $like)
                    ->when($id !== null, fn (Builder $query) => $query->orWhere('id', $id))
                    ->when($stateValues !== [], fn (Builder $query) => $query->orWhereIn('state', $stateValues));
            });
        }
    }

    #[Scope]
    protected function inState(Builder $query, ?MexicanState $state): void
    {
        if ($state !== null) {
            $query->where('state', $state);
        }
    }

    #[Scope]
    protected function inAgeRange(Builder $query, ?AgeRange $range): void
    {
        if ($range === null) {
            return;
        }

        [$minimum, $maximum] = $range->bounds();

        $query->where('age', '>=', $minimum);

        if ($maximum !== null) {
            $query->where('age', '<=', $maximum);
        }
    }

    #[Scope]
    protected function ofType(Builder $query, ?PersonRequestType $type): void
    {
        if ($type !== null) {
            $query->where('type', $type);
        }
    }

    /**
     * Referencia pública de la solicitud, por ejemplo SOL-000012.
     */
    public function reference(): string
    {
        return sprintf('SOL-%06d', $this->id);
    }

    /**
     * «Municipio, Estado», o el lugar en texto libre de las solicitudes anteriores.
     */
    public function placeLabel(): ?string
    {
        $label = collect([$this->municipality, $this->state?->label()])->filter()->implode(', ');

        return $label !== '' ? $label : $this->place;
    }

    /**
     * La media filiación en el orden en que se acostumbra leerla.
     *
     * @return array<string, string>
     */
    public function orderedTraits(): array
    {
        $traits = array_filter($this->traits ?? [], fn ($value): bool => filled($value));
        $position = array_flip(PersonRecord::traitKeys());

        uksort($traits, fn (string $a, string $b): int => ($position[$a] ?? PHP_INT_MAX) <=> ($position[$b] ?? PHP_INT_MAX) ?: strcmp($a, $b));

        return $traits;
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function hasPhoto(): bool
    {
        return filled($this->photo_path);
    }

    /**
     * @return list<string>
     */
    private function stateValuesMatching(string $word): array
    {
        $needle = Str::of($word)->ascii()->lower()->toString();

        return collect(MexicanState::cases())
            ->filter(fn (MexicanState $state): bool => Str::contains(Str::of($state->label())->ascii()->lower()->toString(), $needle))
            ->map(fn (MexicanState $state): string => $state->value)
            ->values()
            ->all();
    }
}
