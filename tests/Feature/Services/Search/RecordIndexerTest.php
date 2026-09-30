<?php

namespace Tests\Feature\Services\Search;

use App\Enums\AgeRange;
use App\Enums\MexicanState;
use App\Enums\RecordType;
use App\Models\PersonRecord;
use App\Services\Search\RecordIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecordIndexerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.records_search' => 'meilisearch',
            'services.meilisearch.host' => 'http://meili.test:7700',
            'services.meilisearch.index' => 'fichas',
            'services.meilisearch.key' => null,
        ]);
    }

    /**
     * Un Meilisearch de mentira: cada tarea que se pide sale terminada.
     */
    private function fakeMeilisearch(bool $indexExists = false): void
    {
        $task = 0;

        Http::fake(function (Request $request) use (&$task, $indexExists) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                $request->method() === 'GET' && $path === '/indexes/fichas' => $indexExists
                    ? Http::response(['uid' => 'fichas'])
                    : Http::response(['message' => 'Index `fichas` not found.'], 404),
                $request->method() === 'GET' && str_starts_with($path, '/tasks/') => Http::response(['status' => 'succeeded']),
                $request->method() === 'GET' && $path === '/indexes/fichas/stats' => Http::response(['numberOfDocuments' => 3]),
                default => Http::response(['taskUid' => ++$task], 202),
            };
        });
    }

    /**
     * @return list<Request>
     */
    private function sent(string $method, string $path): array
    {
        return Http::recorded()
            ->map(fn (array $pair): Request => $pair[0])
            ->filter(fn (Request $request): bool => $request->method() === $method && parse_url($request->url(), PHP_URL_PATH) === $path)
            ->values()
            ->all();
    }

    public function test_builds_the_document_of_a_record_with_only_what_is_needed_to_search_and_filter(): void
    {
        $record = PersonRecord::factory()->create([
            'name' => 'MARIA LOPEZ', 'folio' => 'EN-000007', 'municipality' => 'ZAPOPAN', 'state' => MexicanState::Jalisco,
            'description' => 'Cabello: negro.', 'clothing' => 'CAMISA AZUL', 'distinguishing_marks' => 'TATUAJE',
            'type' => RecordType::MissingPerson, 'age' => 30, 'event_date' => '2026-01-02',
            'source_victim_id' => 'BE74EF5E-38B4-4D37-84E0-E49B45504C10', 'photo_path' => 'imagenes/ab/foto.jpg',
        ]);

        $this->assertSame([
            'id' => $record->id,
            'name' => 'MARIA LOPEZ',
            'folio' => 'EN-000007',
            'municipality' => 'ZAPOPAN',
            'state' => 'jalisco',
            'state_label' => 'Jalisco',
            'distinguishing_marks' => 'TATUAJE',
            'clothing' => 'CAMISA AZUL',
            'description' => 'Cabello: negro.',
            'type' => 'missing_person',
            'age' => 30,
            'event_date' => $record->event_date->timestamp,
        ], app(RecordIndexer::class)->document($record));
    }

    public function test_a_record_without_state_age_or_date_leaves_those_fields_empty(): void
    {
        $record = PersonRecord::factory()->create(['state' => null, 'age' => null, 'event_date' => null]);

        $document = app(RecordIndexer::class)->document($record);

        $this->assertNull($document['state']);
        $this->assertNull($document['state_label']);
        $this->assertNull($document['age']);
        $this->assertNull($document['event_date']);
    }

    public function test_the_settings_filter_by_state_type_and_age_and_forgive_typos_in_names(): void
    {
        $settings = app(RecordIndexer::class)->settings();

        $this->assertSame('name', $settings['searchableAttributes'][0]);
        $this->assertEqualsCanonicalizing(['state', 'type', 'age'], $settings['filterableAttributes']);
        $this->assertTrue($settings['typoTolerance']['enabled']);
        $this->assertSame(['folio'], $settings['typoTolerance']['disableOnAttributes']);
        $this->assertContains('de', $settings['stopWords']);
        $this->assertSame('event_date:desc', end($settings['rankingRules']));
        $this->assertGreaterThanOrEqual(50000, $settings['pagination']['maxTotalHits']);
    }

    public function test_creates_the_index_applies_the_settings_and_sends_only_published_records(): void
    {
        $this->fakeMeilisearch();
        $published = PersonRecord::factory()->count(3)->create();
        $hidden = PersonRecord::factory()->unpublished()->create();

        $result = app(RecordIndexer::class)->sync();

        $this->assertSame(['indexed' => 3, 'removed' => 1, 'total' => 3], $result);
        $this->assertCount(1, $this->sent('POST', '/indexes'));
        $this->assertCount(1, $this->sent('PATCH', '/indexes/fichas/settings'));

        $documents = $this->sent('POST', '/indexes/fichas/documents');
        $this->assertCount(1, $documents);
        $this->assertEqualsCanonicalizing($published->pluck('id')->all(), array_column($documents[0]->data(), 'id'));

        $deletions = $this->sent('POST', '/indexes/fichas/documents/delete-batch');
        $this->assertCount(1, $deletions);
        $this->assertSame([$hidden->id], $deletions[0]->data());
    }

    public function test_reuses_an_index_that_already_exists(): void
    {
        $this->fakeMeilisearch(indexExists: true);
        PersonRecord::factory()->create();

        app(RecordIndexer::class)->sync();

        $this->assertSame([], $this->sent('POST', '/indexes'));
        $this->assertSame([], $this->sent('DELETE', '/indexes/fichas'));
        $this->assertCount(1, $this->sent('PATCH', '/indexes/fichas/settings'));
    }

    public function test_a_fresh_sync_drops_and_recreates_the_index_and_has_nothing_to_remove(): void
    {
        $this->fakeMeilisearch(indexExists: true);
        PersonRecord::factory()->create();
        PersonRecord::factory()->unpublished()->create();

        $result = app(RecordIndexer::class)->sync(fresh: true);

        $this->assertSame(0, $result['removed']);
        $this->assertCount(1, $this->sent('DELETE', '/indexes/fichas'));
        $this->assertCount(1, $this->sent('POST', '/indexes'));
        $this->assertSame([], $this->sent('POST', '/indexes/fichas/documents/delete-batch'));
    }

    public function test_a_fresh_sync_does_not_fail_when_there_is_no_index_to_drop(): void
    {
        $this->fakeMeilisearch(indexExists: false);
        PersonRecord::factory()->create();

        app(RecordIndexer::class)->sync(fresh: true);

        $this->assertSame([], $this->sent('DELETE', '/indexes/fichas'));
        $this->assertCount(1, $this->sent('POST', '/indexes'));
    }

    public function test_sends_the_records_in_chunks_and_reports_each_one(): void
    {
        $this->fakeMeilisearch();
        PersonRecord::factory()->count(3)->create();
        $progress = [];

        app(RecordIndexer::class)->sync(chunk: 2, advance: function (int $count) use (&$progress): void {
            $progress[] = $count;
        });

        $this->assertCount(2, $this->sent('POST', '/indexes/fichas/documents'));
        $this->assertSame([2, 1], $progress);
    }

    public function test_indexes_the_filters_that_a_search_by_age_range_needs(): void
    {
        $this->fakeMeilisearch();
        PersonRecord::factory()->ofAge(25)->create();

        app(RecordIndexer::class)->sync();

        $document = $this->sent('POST', '/indexes/fichas/documents')[0]->data()[0];
        $this->assertSame(AgeRange::YoungAdult, AgeRange::fromAge($document['age']));
    }
}
