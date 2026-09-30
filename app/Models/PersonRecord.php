<?php

namespace App\Models;

use App\Enums\AgeRange;
use App\Enums\DisappearanceStatus;
use App\Enums\MexicanState;
use App\Enums\RecordType;
use App\Enums\Sex;
use Database\Factories\PersonRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

#[Fillable([
    'folio',
    'type',
    'disappearance_status',
    'sex',
    'name',
    'age',
    'current_age',
    'state',
    'municipality',
    'event_date',
    'description',
    'traits',
    'clothing',
    'distinguishing_marks',
    'authority',
    'photo_sha256',
    'photo_path',
    'source_victim_id',
    'source_report_id',
    'source_agency_id',
    'published_at',
])]
#[Hidden(['source_victim_id', 'source_report_id', 'source_agency_id'])]
#[RouteKey('folio')]
class PersonRecord extends Model
{
    /** @use HasFactory<PersonRecordFactory> */
    use HasFactory;

    /**
     * Nombres legibles de los rasgos de la media filiación.
     */
    private const TRAIT_LABELS = [
        'complexion' => 'Complexión',
        'cara' => 'Cara',
        'color_de_la_piel' => 'Color de piel',
        'cabello' => 'Cabello',
        'ojos' => 'Ojos',
        'nariz' => 'Nariz',
        'boca' => 'Boca',
        'labios' => 'Labios',
        'estatura' => 'Estatura',
        'peso' => 'Peso',
    ];

    public const PUBLISHED_COUNT_KEY = 'records.published-count';

    private const FULLTEXT_INDEX = 'person_records_search_fulltext';

    private const FULLTEXT_KEY = 'records.fulltext-index';

    /**
     * Columnas del índice de texto completo (ver su migración).
     */
    private const FULLTEXT_COLUMNS = ['name', 'municipality', 'description', 'state'];

    /**
     * Claves de los rasgos de la media filiación, en su orden de lectura.
     *
     * @return list<string>
     */
    public static function traitKeys(): array
    {
        return array_keys(self::TRAIT_LABELS);
    }

    public static function traitLabel(string $key): string
    {
        return self::TRAIT_LABELS[$key] ?? Str::of($key)->replace('_', ' ')->ucfirst()->toString();
    }

    /**
     * La media filiación en el orden en que se acostumbra leerla. MySQL guarda
     * los campos de un JSON ordenados por su longitud, no como llegaron.
     *
     * @return array<string, string>
     */
    public function orderedTraits(): array
    {
        $traits = $this->traits ?? [];
        $position = array_flip(array_keys(self::TRAIT_LABELS));

        uksort($traits, fn (string $a, string $b): int => ($position[$a] ?? PHP_INT_MAX) <=> ($position[$b] ?? PHP_INT_MAX) ?: strcmp($a, $b));

        return $traits;
    }

    /**
     * Cantidad de fichas públicas. El catálogo solo cambia al importar, así que
     * el conteo se guarda unos minutos en vez de recontar en cada visita.
     */
    public static function publishedCount(): int
    {
        return Cache::remember(
            self::PUBLISHED_COUNT_KEY,
            now()->addMinutes(10),
            fn (): int => static::query()->published()->count(),
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RecordType::class,
            'disappearance_status' => DisappearanceStatus::class,
            'sex' => Sex::class,
            'state' => MexicanState::class,
            'event_date' => 'date',
            'traits' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Fichas visibles para el público.
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->whereNotNull('published_at');
    }

    /**
     * Cada palabra debe aparecer en el folio, el nombre, el lugar, la
     * descripción o el estado; el orden de las palabras no importa.
     *
     * En MySQL se usa el índice de texto completo (las palabras se buscan por
     * su inicio: «mar» encuentra «maría»); sin él, o en otras bases, se busca
     * cada palabra con LIKE en cualquier parte del texto.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $words = preg_split('/\s+/u', trim((string) $term), 6, PREG_SPLIT_NO_EMPTY) ?: [];

        // El índice no guarda palabras de una o dos letras: si todas lo son,
        // se busca con LIKE.
        if ($this->hasWordsForFullText($words) && $this->usesFullText($query)) {
            $this->searchFullText($query, $words);

            return;
        }

        foreach ($words as $word) {
            $like = '%'.addcslashes($word, '\\%_').'%';
            $stateValues = $this->stateValuesMatching($word);

            $query->where(function (Builder $query) use ($like, $stateValues): void {
                $query->where('folio', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('municipality', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->when($stateValues !== [], fn (Builder $query) => $query->orWhereIn('state', $stateValues));
            });
        }
    }

    private function usesFullText(Builder $query): bool
    {
        if ($query->getConnection()->getDriverName() !== 'mysql' || ! config('services.records_fulltext')) {
            return false;
        }

        // Solo se recuerda el sí: si la migración aún no corre, se vuelve a mirar.
        if (Cache::get(self::FULLTEXT_KEY) === true) {
            return true;
        }

        $exists = Schema::hasIndex('person_records', self::FULLTEXT_INDEX);

        if ($exists) {
            Cache::put(self::FULLTEXT_KEY, true, now()->addHour());
        }

        return $exists;
    }

    /**
     * Sin los símbolos que tienen significado propio en el modo booleano.
     */
    private function plainWord(string $word): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', $word) ?? '';
    }

    /**
     * @param  list<string>  $words
     */
    private function hasWordsForFullText(array $words): bool
    {
        foreach ($words as $word) {
            if (mb_strlen($this->plainWord($word)) >= 3) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $words
     */
    private function searchFullText(Builder $query, array $words): void
    {
        $terms = [];

        foreach ($words as $word) {
            // Un folio (EN-000123) o un número se busca en el folio.
            if (preg_match('/^(en-?)?\d+$/i', $word) === 1) {
                $query->where('folio', 'like', '%'.addcslashes($word, '\\%_').'%');

                continue;
            }

            // Las palabras de una o dos letras («de», «la») no se buscan: sobran.
            if (mb_strlen($this->plainWord($word)) >= 3) {
                $terms[] = '+'.$this->plainWord($word).'*';
            }
        }

        if ($terms !== []) {
            $query->whereRaw(
                'match('.implode(', ', self::FULLTEXT_COLUMNS).') against (? in boolean mode)',
                [implode(' ', $terms)],
            );
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

    /**
     * Más recientes primero; en orden descendente las fichas sin fecha
     * quedan al final y el orden usa el índice de `event_date`.
     */
    #[Scope]
    protected function latestEvents(Builder $query): void
    {
        $query->orderByDesc('event_date')->orderByDesc('id');
    }

    public function hasPhoto(): bool
    {
        return filled($this->photo_path);
    }

    /**
     * Valores de entidad cuyo nombre contiene la palabra buscada, sin
     * distinguir mayúsculas ni acentos.
     *
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
