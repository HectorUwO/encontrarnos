<?php

namespace App\Services\Search;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente mínimo de la API HTTP de Meilisearch: solo lo que usa la aplicación,
 * sin paquetes adicionales.
 */
class MeilisearchClient
{
    /**
     * Tras un fallo se deja de intentar unos segundos, para que las visitas
     * siguientes no esperen a que venza el tiempo de espera una y otra vez.
     */
    private const DOWN_KEY = 'search.meilisearch.down';

    private const DOWN_SECONDS = 30;

    /**
     * Si la búsqueda de fichas está configurada para usar Meilisearch.
     */
    public function configured(): bool
    {
        return config('services.records_search') === 'meilisearch';
    }

    /**
     * Configurado y sin fallos recientes.
     */
    public function enabled(): bool
    {
        return $this->configured() && ! Cache::has(self::DOWN_KEY);
    }

    public function markDown(): void
    {
        Cache::put(self::DOWN_KEY, true, now()->addSeconds(self::DOWN_SECONDS));
    }

    public function index(): string
    {
        return (string) config('services.meilisearch.index');
    }

    public function host(): string
    {
        return rtrim((string) config('services.meilisearch.host'), '/');
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array{hits: list<array<string, mixed>>, totalHits: int}
     */
    public function search(array $parameters): array
    {
        return $this->http()->post("/indexes/{$this->index()}/search", $parameters)->json();
    }

    public function indexExists(): bool
    {
        try {
            $this->http()->get("/indexes/{$this->index()}");

            return true;
        } catch (RequestException $exception) {
            if ($exception->response->status() === 404) {
                return false;
            }

            throw $exception;
        }
    }

    /**
     * @return int Tarea que crea el índice.
     */
    public function createIndex(): int
    {
        return (int) $this->http()->post('/indexes', ['uid' => $this->index(), 'primaryKey' => 'id'])->json('taskUid');
    }

    /**
     * @return int Tarea que borra el índice (falla si el índice no existe).
     */
    public function deleteIndex(): int
    {
        return (int) $this->http()->delete("/indexes/{$this->index()}")->json('taskUid');
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return int Tarea que aplica los ajustes.
     */
    public function updateSettings(array $settings): int
    {
        return (int) $this->http()->patch("/indexes/{$this->index()}/settings", $settings)->json('taskUid');
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     * @return int Tarea que agrega o actualiza los documentos.
     */
    public function addDocuments(array $documents): int
    {
        return (int) $this->http(120)->post("/indexes/{$this->index()}/documents?primaryKey=id", $documents)->json('taskUid');
    }

    /**
     * @param  list<int>  $ids
     * @return int Tarea que borra los documentos.
     */
    public function deleteDocuments(array $ids): int
    {
        return (int) $this->http(60)->post("/indexes/{$this->index()}/documents/delete-batch", $ids)->json('taskUid');
    }

    public function documentCount(): int
    {
        return (int) $this->http()->get("/indexes/{$this->index()}/stats")->json('numberOfDocuments');
    }

    /**
     * Espera a que Meilisearch termine una tarea; Meilisearch las procesa en
     * segundo plano y de una en una.
     *
     * @throws RuntimeException Si la tarea falla o tarda demasiado.
     */
    public function waitForTask(int $task, int $seconds = 900, int $pollMilliseconds = 300): void
    {
        $deadline = time() + $seconds;

        do {
            $status = $this->http()->get("/tasks/{$task}")->json();

            if (($status['status'] ?? null) === 'succeeded') {
                return;
            }

            if (in_array($status['status'] ?? null, ['failed', 'canceled'], true)) {
                throw new RuntimeException('Meilisearch no pudo completar la tarea '.$task.': '.($status['error']['message'] ?? $status['status']));
            }

            usleep($pollMilliseconds * 1000);
        } while (time() < $deadline);

        throw new RuntimeException("Meilisearch tardó demasiado en la tarea {$task}.");
    }

    private function http(?int $timeout = null): PendingRequest
    {
        $key = config('services.meilisearch.key');

        return Http::baseUrl($this->host())
            ->acceptJson()
            ->asJson()
            ->timeout($timeout ?? (int) config('services.meilisearch.timeout'))
            ->when($key, fn (PendingRequest $request): PendingRequest => $request->withToken($key))
            ->throw();
    }
}
