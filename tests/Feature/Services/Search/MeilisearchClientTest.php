<?php

namespace Tests\Feature\Services\Search;

use App\Services\Search\MeilisearchClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class MeilisearchClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.records_search' => 'meilisearch',
            'services.meilisearch.host' => 'http://meili.test:7700/',
            'services.meilisearch.index' => 'fichas',
            'services.meilisearch.key' => null,
        ]);
    }

    public function test_is_enabled_only_when_the_search_of_records_is_set_to_meilisearch(): void
    {
        $client = new MeilisearchClient;

        $this->assertTrue($client->configured());
        $this->assertTrue($client->enabled());

        config(['services.records_search' => 'database']);

        $this->assertFalse($client->configured());
        $this->assertFalse($client->enabled());
    }

    public function test_stops_being_enabled_for_a_while_after_a_failure_without_losing_its_configuration(): void
    {
        $client = new MeilisearchClient;

        $client->markDown();

        $this->assertTrue($client->configured());
        $this->assertFalse($client->enabled());
    }

    public function test_searches_the_index_sending_the_key_as_a_bearer_token(): void
    {
        config(['services.meilisearch.key' => 'llave-secreta']);
        Http::fake(['meili.test:7700/*' => Http::response(['hits' => [['id' => 5]], 'totalHits' => 1])]);

        $result = (new MeilisearchClient)->search(['q' => 'maria', 'page' => 2]);

        $this->assertSame([['id' => 5]], $result['hits']);
        $this->assertSame(1, $result['totalHits']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'http://meili.test:7700/indexes/fichas/search'
            && $request['q'] === 'maria'
            && $request['page'] === 2
            && $request->hasHeader('Authorization', 'Bearer llave-secreta'));
    }

    public function test_sends_no_authorization_header_when_there_is_no_key(): void
    {
        Http::fake(['*' => Http::response(['hits' => [], 'totalHits' => 0])]);

        (new MeilisearchClient)->search(['q' => 'maria']);

        Http::assertSent(fn (Request $request): bool => ! $request->hasHeader('Authorization'));
    }

    public function test_raises_the_error_when_meilisearch_answers_with_a_failure(): void
    {
        Http::fake(['*' => Http::response(['message' => 'boom'], 500)]);

        $this->expectException(RequestException::class);

        (new MeilisearchClient)->search(['q' => 'maria']);
    }

    public function test_knows_whether_the_index_exists(): void
    {
        Http::fake([
            'meili.test:7700/indexes/fichas' => Http::sequence()
                ->push(['uid' => 'fichas'], 200)
                ->push(['message' => 'Index `fichas` not found.'], 404),
        ]);

        $client = new MeilisearchClient;

        $this->assertTrue($client->indexExists());
        $this->assertFalse($client->indexExists());
    }

    public function test_does_not_hide_other_errors_when_asking_for_the_index(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $this->expectException(RequestException::class);

        (new MeilisearchClient)->indexExists();
    }

    public function test_creates_the_index_with_id_as_its_primary_key_and_returns_the_task(): void
    {
        Http::fake(['*' => Http::response(['taskUid' => 3], 202)]);

        $this->assertSame(3, (new MeilisearchClient)->createIndex());

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://meili.test:7700/indexes'
            && $request['uid'] === 'fichas'
            && $request['primaryKey'] === 'id');
    }

    public function test_sends_settings_documents_and_deletions_to_the_index(): void
    {
        Http::fake(['*' => Http::response(['taskUid' => 9], 202)]);
        $client = new MeilisearchClient;

        $this->assertSame(9, $client->updateSettings(['stopWords' => ['de']]));
        $this->assertSame(9, $client->addDocuments([['id' => 1, 'name' => 'ANA']]));
        $this->assertSame(9, $client->deleteDocuments([4, 5]));
        $this->assertSame(9, $client->deleteIndex());

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
            && $request->url() === 'http://meili.test:7700/indexes/fichas/settings'
            && $request['stopWords'] === ['de']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'http://meili.test:7700/indexes/fichas/documents?primaryKey=id'
            && $request->data() === [['id' => 1, 'name' => 'ANA']]);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://meili.test:7700/indexes/fichas/documents/delete-batch'
            && $request->data() === [4, 5]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->url() === 'http://meili.test:7700/indexes/fichas');
    }

    public function test_counts_the_documents_of_the_index(): void
    {
        Http::fake(['*/stats' => Http::response(['numberOfDocuments' => 51500, 'isIndexing' => false])]);

        $this->assertSame(51500, (new MeilisearchClient)->documentCount());
    }

    public function test_waits_for_a_task_until_it_succeeds(): void
    {
        Http::fake([
            'meili.test:7700/tasks/7' => Http::sequence()
                ->push(['status' => 'enqueued'])
                ->push(['status' => 'processing'])
                ->push(['status' => 'succeeded']),
        ]);

        (new MeilisearchClient)->waitForTask(7, pollMilliseconds: 1);

        Http::assertSentCount(3);
    }

    public function test_reports_why_a_task_failed(): void
    {
        Http::fake(['*/tasks/7' => Http::response(['status' => 'failed', 'error' => ['message' => 'Index `fichas` not found.']])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Index `fichas` not found.');

        (new MeilisearchClient)->waitForTask(7, pollMilliseconds: 1);
    }

    public function test_gives_up_on_a_task_that_never_finishes(): void
    {
        Http::fake(['*/tasks/7' => Http::response(['status' => 'processing'])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tardó demasiado');

        (new MeilisearchClient)->waitForTask(7, seconds: 0, pollMilliseconds: 1);
    }
}
