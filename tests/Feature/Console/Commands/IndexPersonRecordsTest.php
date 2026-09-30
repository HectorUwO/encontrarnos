<?php

namespace Tests\Feature\Console\Commands;

use App\Models\PersonRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IndexPersonRecordsTest extends TestCase
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

    private function fakeMeilisearch(bool $indexExists = false, string $taskStatus = 'succeeded'): void
    {
        $task = 0;

        Http::fake(function (Request $request) use (&$task, $indexExists, $taskStatus) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                $request->method() === 'GET' && $path === '/indexes/fichas' => $indexExists
                    ? Http::response(['uid' => 'fichas'])
                    : Http::response(['message' => 'Index `fichas` not found.'], 404),
                $request->method() === 'GET' && str_starts_with($path, '/tasks/') => Http::response([
                    'status' => $taskStatus,
                    'error' => ['message' => 'Índice dañado.'],
                ]),
                $request->method() === 'GET' && $path === '/indexes/fichas/stats' => Http::response(['numberOfDocuments' => 2]),
                default => Http::response(['taskUid' => ++$task], 202),
            };
        });
    }

    public function test_syncs_the_public_records_and_reports_the_result(): void
    {
        $this->fakeMeilisearch();
        PersonRecord::factory()->count(2)->create();
        PersonRecord::factory()->unpublished()->create();

        $this->artisan('records:index')
            ->expectsOutputToContain('Fichas enviadas al índice')
            ->expectsOutputToContain('Fichas retiradas del índice')
            ->expectsOutputToContain('Documentos en el índice')
            ->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/indexes/fichas/documents?primaryKey=id')
            && count($request->data()) === 2);
    }

    public function test_the_fresh_option_starts_from_an_empty_index(): void
    {
        $this->fakeMeilisearch(indexExists: true);
        PersonRecord::factory()->create();

        $this->artisan('records:index --fresh')->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/indexes/fichas'));
    }

    public function test_warns_when_the_search_is_not_set_to_use_meilisearch_but_indexes_anyway(): void
    {
        config(['services.records_search' => 'database']);
        $this->fakeMeilisearch();
        PersonRecord::factory()->create();

        $this->artisan('records:index')
            ->expectsOutputToContain('RECORDS_SEARCH no es «meilisearch»')
            ->assertSuccessful();
    }

    public function test_explains_that_meilisearch_must_be_on_when_it_cannot_be_reached(): void
    {
        Http::fake(function (): never {
            throw new ConnectionException('No se pudo conectar.');
        });

        $this->artisan('records:index')
            ->expectsOutputToContain('No se pudo conectar con Meilisearch en http://meili.test:7700')
            ->assertFailed();
    }

    public function test_reports_why_a_task_failed(): void
    {
        $this->fakeMeilisearch(taskStatus: 'failed');
        PersonRecord::factory()->create();

        $this->artisan('records:index')
            ->expectsOutputToContain('Índice dañado.')
            ->assertFailed();
    }
}
