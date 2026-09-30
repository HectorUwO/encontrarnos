<?php

namespace App\Services\Search;

use App\Models\PersonRecord;
use Illuminate\Support\Collection;

/**
 * Mantiene el índice de Meilisearch igual que las fichas publicadas. El índice
 * solo guarda lo necesario para buscar y filtrar; los datos que se muestran se
 * leen de MySQL, así que la lista blanca de campos públicos sigue viviendo en
 * PersonRecordResource y una ficha retirada nunca sale por el índice.
 */
class RecordIndexer
{
    private const DELETE_CHUNK = 5000;

    public function __construct(private readonly MeilisearchClient $client) {}

    /**
     * Ajustes del índice, de lo más a lo menos importante: primero el nombre,
     * luego el lugar y por último las descripciones.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return [
            'searchableAttributes' => [
                'name', 'folio', 'municipality', 'state_label', 'distinguishing_marks', 'clothing', 'description',
            ],
            'filterableAttributes' => ['state', 'type', 'age'],
            // A igual relevancia, primero lo más reciente.
            'rankingRules' => ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness', 'event_date:desc'],
            'stopWords' => ['de', 'del', 'la', 'las', 'los', 'el', 'y', 'en', 'a', 'al'],
            // Los nombres se escriben de muchas formas («Hernandes», «Gonzales»).
            'typoTolerance' => [
                'enabled' => true,
                'minWordSizeForTypos' => ['oneTypo' => 4, 'twoTypos' => 8],
                'disableOnAttributes' => ['folio'],
            ],
            'pagination' => ['maxTotalHits' => 60000],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function document(PersonRecord $record): array
    {
        return [
            'id' => $record->id,
            'name' => $record->name,
            'folio' => $record->folio,
            'municipality' => $record->municipality,
            'state' => $record->state?->value,
            'state_label' => $record->state?->label(),
            'distinguishing_marks' => $record->distinguishing_marks,
            'clothing' => $record->clothing,
            'description' => $record->description,
            'type' => $record->type->value,
            'age' => $record->age,
            'event_date' => $record->event_date?->timestamp,
        ];
    }

    /**
     * Deja el índice con las fichas publicadas: agrega o actualiza las que
     * están y quita las que ya no se publican.
     *
     * @param  callable(int): void|null  $advance  Se llama con las fichas de cada lote enviado.
     * @return array{indexed: int, removed: int, total: int}
     */
    public function sync(int $chunk = 2000, bool $fresh = false, ?callable $advance = null): array
    {
        $exists = $this->client->indexExists();

        if ($fresh && $exists) {
            $this->client->waitForTask($this->client->deleteIndex());
            $exists = false;
        }

        if (! $exists) {
            $this->client->waitForTask($this->client->createIndex());
        }

        $this->client->waitForTask($this->client->updateSettings($this->settings()));

        $tasks = [];
        $indexed = 0;

        PersonRecord::query()->published()->chunkById($chunk, function (Collection $records) use (&$tasks, &$indexed, $advance): void {
            $tasks[] = $this->client->addDocuments($records->map(fn (PersonRecord $record): array => $this->document($record))->all());
            $indexed += $records->count();

            if ($advance !== null) {
                $advance($records->count());
            }
        });

        $removed = 0;

        if (! $fresh) {
            PersonRecord::query()->whereNull('published_at')->select('id')->chunkById(self::DELETE_CHUNK, function (Collection $records) use (&$tasks, &$removed): void {
                $tasks[] = $this->client->deleteDocuments($records->pluck('id')->all());
                $removed += $records->count();
            });
        }

        foreach ($tasks as $task) {
            $this->client->waitForTask($task);
        }

        return ['indexed' => $indexed, 'removed' => $removed, 'total' => $this->client->documentCount()];
    }
}
