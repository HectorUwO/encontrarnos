<?php

namespace Tests\Feature\Services\Search;

use App\Enums\AgeRange;
use App\Enums\MexicanState;
use App\Enums\RecordType;
use App\Models\PersonRecord;
use App\Services\Search\RecordSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecordSearchTest extends TestCase
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

    private function search(?string $term = 'maria', ?MexicanState $state = null, ?AgeRange $range = null, ?RecordType $type = null): LengthAwarePaginator
    {
        return app(RecordSearch::class)->paginate($term, $state, $range, $type, 12);
    }

    /**
     * @param  list<int>  $ids
     */
    private function meilisearchAnswers(array $ids, ?int $total = null): void
    {
        Http::fake(['meili.test:7700/*' => Http::response([
            'hits' => array_map(fn (int $id): array => ['id' => $id], $ids),
            'totalHits' => $total ?? count($ids),
        ])]);
    }

    public function test_searches_the_database_when_meilisearch_is_not_configured(): void
    {
        config(['services.records_search' => 'database']);
        Http::fake();
        PersonRecord::factory()->create(['name' => 'MARIA LOPEZ']);
        PersonRecord::factory()->create(['name' => 'JUAN PEREZ']);

        $found = $this->search('maria');

        $this->assertSame(['MARIA LOPEZ'], $found->pluck('name')->all());
        Http::assertNothingSent();
    }

    public function test_lists_from_the_database_when_there_is_nothing_to_search_even_with_meilisearch_enabled(): void
    {
        Http::fake();
        PersonRecord::factory()->create(['folio' => 'EN-000001', 'event_date' => '2026-01-01']);
        PersonRecord::factory()->create(['folio' => 'EN-000002', 'event_date' => '2026-02-01']);

        $this->assertSame(['EN-000002', 'EN-000001'], $this->search(null)->pluck('folio')->all());
        $this->assertSame(2, $this->search('   ')->total());
        Http::assertNothingSent();
    }

    public function test_asks_meilisearch_and_keeps_the_order_in_which_it_answers(): void
    {
        [$first, $second, $third] = PersonRecord::factory()->count(3)->create()->all();
        $this->meilisearchAnswers([$third->id, $first->id, $second->id], total: 30);

        $found = $this->search('maria lopez');

        $this->assertSame([$third->id, $first->id, $second->id], $found->pluck('id')->all());
        $this->assertSame(30, $found->total());
        $this->assertSame(12, $found->perPage());
        $this->assertSame(1, $found->currentPage());
        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://meili.test:7700/indexes/fichas/search'
            && $request['q'] === 'maria lopez'
            && $request['matchingStrategy'] === 'all'
            && $request['page'] === 1
            && $request['hitsPerPage'] === 12
            && $request['attributesToRetrieve'] === ['id']
            && $request['filter'] === []);
    }

    public function test_asks_for_the_page_the_visitor_is_on(): void
    {
        $this->meilisearchAnswers([], total: 40);
        request()->merge(['page' => 3]);

        $found = $this->search();

        $this->assertSame(3, $found->currentPage());
        Http::assertSent(fn (Request $request): bool => $request['page'] === 3);
    }

    /**
     * @return array<string, array{MexicanState|null, RecordType|null, AgeRange|null, list<string>}>
     */
    public static function filters(): array
    {
        return [
            'none' => [null, null, null, []],
            'state' => [MexicanState::Jalisco, null, null, ["state = 'jalisco'"]],
            'state with a composite name' => [MexicanState::Mexico, null, null, ["state = 'estado-de-mexico'"]],
            'type' => [null, RecordType::IdentificationRequest, null, ["type = 'identification_request'"]],
            'minors' => [null, null, AgeRange::Minor, ['age >= 0', 'age <= 17']],
            'young adults' => [null, null, AgeRange::YoungAdult, ['age >= 18', 'age <= 29']],
            'over forty has no ceiling' => [null, null, AgeRange::Mature, ['age >= 40']],
            'all together' => [MexicanState::Sinaloa, RecordType::MissingPerson, AgeRange::Adult, ["state = 'sinaloa'", "type = 'missing_person'", 'age >= 30', 'age <= 39']],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('filters')]
    public function test_turns_the_filters_into_meilisearch_filters(?MexicanState $state, ?RecordType $type, ?AgeRange $range, array $expected): void
    {
        $this->meilisearchAnswers([]);

        $this->search('maria', $state, $range, $type);

        Http::assertSent(fn (Request $request): bool => $request['filter'] === $expected);
    }

    public function test_never_shows_a_record_that_is_no_longer_published_even_if_the_index_still_has_it(): void
    {
        $visible = PersonRecord::factory()->create();
        $withdrawn = PersonRecord::factory()->unpublished()->create();
        $this->meilisearchAnswers([$withdrawn->id, $visible->id]);

        $this->assertSame([$visible->id], $this->search()->pluck('id')->all());
    }

    public function test_an_empty_answer_gives_an_empty_page(): void
    {
        $this->meilisearchAnswers([]);

        $found = $this->search('xyzqwerty');

        $this->assertSame(0, $found->total());
        $this->assertTrue($found->isEmpty());
    }

    public function test_falls_back_to_the_database_when_meilisearch_fails_and_stops_trying_for_a_while(): void
    {
        Http::fake(['*' => Http::response(['message' => 'boom'], 500)]);
        PersonRecord::factory()->create(['name' => 'MARIA LOPEZ']);

        $first = $this->search('maria');
        $second = $this->search('maria');

        $this->assertSame(['MARIA LOPEZ'], $first->pluck('name')->all());
        $this->assertSame(['MARIA LOPEZ'], $second->pluck('name')->all());
        Http::assertSentCount(1);
        $this->assertTrue(Cache::has('search.meilisearch.down'));
    }

    public function test_falls_back_to_the_database_when_meilisearch_cannot_be_reached(): void
    {
        Http::fake(function (): never {
            throw new ConnectionException('No se pudo conectar.');
        });
        PersonRecord::factory()->create(['name' => 'MARIA LOPEZ']);

        $this->assertSame(['MARIA LOPEZ'], $this->search('maria')->pluck('name')->all());
    }
}
