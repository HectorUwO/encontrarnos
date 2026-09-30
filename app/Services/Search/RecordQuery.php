<?php

namespace App\Services\Search;

use App\Enums\AgeRange;
use App\Enums\DisappearanceStatus;
use App\Enums\MexicanState;
use App\Enums\Sex;

/**
 * Todo lo que una persona puede pedirle al catálogo de fichas: texto, filtros
 * y orden.
 */
final readonly class RecordQuery
{
    public const SORTS = ['recent', 'oldest', 'name', 'age_asc', 'age_desc', 'added'];

    public function __construct(
        public ?string $term = null,
        public ?MexicanState $state = null,
        public ?AgeRange $ageRange = null,
        public ?int $ageFrom = null,
        public ?int $ageTo = null,
        public ?Sex $sex = null,
        public ?DisappearanceStatus $status = null,
        public bool $withPhoto = false,
        public ?string $from = null,
        public ?string $to = null,
        public ?string $municipality = null,
        public ?string $authority = null,
        public ?string $nationality = null,
        public bool $withDisability = false,
        public ?string $registryPublish = null,
        public string $sort = 'recent',
    ) {}

    /**
     * Los filtros que Meilisearch no indexa (y el orden) obligan a buscar en
     * la base de datos.
     */
    public function needsDatabase(): bool
    {
        return $this->ageFrom !== null
            || $this->ageTo !== null
            || $this->sex !== null
            || $this->status !== null
            || $this->withPhoto
            || $this->from !== null
            || $this->to !== null
            || $this->municipality !== null
            || $this->authority !== null
            || $this->nationality !== null
            || $this->withDisability
            || $this->registryPublish !== null
            || $this->sort !== 'recent';
    }
}
