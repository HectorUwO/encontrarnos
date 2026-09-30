<?php

namespace App\Services\Search;

use App\Enums\AgeRange;
use App\Enums\MexicanState;
use App\Enums\RecordType;
use App\Models\PersonRecord;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Búsqueda pública de fichas. Con texto usa Meilisearch (si está configurado
 * y responde); sin texto, o si Meilisearch falla, usa la base de datos.
 */
class RecordSearch
{
    public function __construct(private readonly MeilisearchClient $meilisearch) {}

    public function paginate(?string $term, ?MexicanState $state, ?AgeRange $range, ?RecordType $type, int $perPage): LengthAwarePaginator
    {
        $term = trim((string) $term);

        if ($term !== '' && $this->meilisearch->enabled()) {
            try {
                return $this->fromMeilisearch($term, $state, $range, $type, $perPage);
            } catch (Throwable $exception) {
                $this->meilisearch->markDown();

                Log::warning('Meilisearch no respondió; la búsqueda usa la base de datos.', ['error' => $exception->getMessage()]);
            }
        }

        return PersonRecord::query()
            ->published()
            ->search($term)
            ->inState($state)
            ->inAgeRange($range)
            ->ofType($type)
            ->latestEvents()
            ->paginate($perPage);
    }

    private function fromMeilisearch(string $term, ?MexicanState $state, ?AgeRange $range, ?RecordType $type, int $perPage): LengthAwarePaginator
    {
        $page = Paginator::resolveCurrentPage();

        $response = $this->meilisearch->search([
            'q' => $term,
            // Como en la base de datos, deben aparecer todas las palabras.
            'matchingStrategy' => 'all',
            'filter' => $this->filters($state, $range, $type),
            'page' => $page,
            'hitsPerPage' => $perPage,
            'attributesToRetrieve' => ['id'],
        ]);

        $ids = array_map('intval', array_column($response['hits'], 'id'));

        // Las fichas salen de MySQL: si el índice quedó atrasado y una ficha ya
        // no está publicada, simplemente no aparece.
        $records = $ids === []
            ? collect()
            : PersonRecord::query()
                ->published()
                ->whereIn('id', $ids)
                ->get()
                ->sortBy(fn (PersonRecord $record): int => (int) array_search($record->id, $ids, true))
                ->values();

        return new LengthAwarePaginator($records, (int) $response['totalHits'], $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
        ]);
    }

    /**
     * @return list<string>
     */
    private function filters(?MexicanState $state, ?AgeRange $range, ?RecordType $type): array
    {
        $filters = [];

        if ($state !== null) {
            $filters[] = "state = '{$state->value}'";
        }

        if ($type !== null) {
            $filters[] = "type = '{$type->value}'";
        }

        if ($range !== null) {
            [$minimum, $maximum] = $range->bounds();

            $filters[] = "age >= {$minimum}";

            if ($maximum !== null) {
                $filters[] = "age <= {$maximum}";
            }
        }

        return $filters;
    }
}
