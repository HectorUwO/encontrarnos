<?php

namespace App\Console\Commands;

use App\Models\PersonRecord;
use App\Models\PersonRequest;
use App\Services\PhotoSearch\CompreFaceClient;
use App\Services\PhotoSearch\FaceIndexer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;

#[Signature('faces:index {--prune : Retira rostros de fichas ocultas, cerradas o eliminadas} {--refresh : Actualiza también rostros ya registrados} {--limit=0 : Máximo de rostros indexados correctamente, 0 sin límite} {--catalog=all : all, records o requests} {--timings : Muestra el tiempo de indexación de cada fotografía} {--dry-run : Revisa archivos y pendientes sin procesar ni modificar rostros} {--progress : Muestra avance cada 100 fichas} {--report : Guarda un informe privado JSONL con resultados y tiempos}')]
#[Description('Sincroniza desaparecidos y solicitudes públicas con CompreFace')]
class IndexPersonFaces extends Command
{
    private ?LoggerInterface $reportLogger = null;

    public function handle(FaceIndexer $indexer, CompreFaceClient $client): int
    {
        $this->reportLogger = null;
        if (! $client->isConfigured()) {
            $this->components->error('Configura COMPREFACE_ENABLED y COMPREFACE_API_KEY.');

            return self::FAILURE;
        }

        $catalog = $this->option('catalog');
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if (! in_array($catalog, ['all', 'records', 'requests'], true) || $limit === false || $limit < 0) {
            $this->components->error('Usa --catalog=all|records|requests y un límite entero no negativo.');

            return self::FAILURE;
        }
        $models = match ($catalog) {
            'records' => [PersonRecord::class],
            'requests' => [PersonRequest::class],
            default => [PersonRecord::class, PersonRequest::class],
        };

        if ($this->option('dry-run')) {
            return $this->runIndex($indexer, $client, $models, $limit);
        }

        $lock = Cache::lock('face-index:catalog', 86400);
        if (! $lock->get()) {
            $this->components->error('Ya hay una indexación de rostros en ejecución.');

            return self::FAILURE;
        }

        try {
            return $this->runIndex($indexer, $client, $models, $limit);
        } finally {
            $lock->release();
        }
    }

    /** @param list<class-string<PersonRecord>|class-string<PersonRequest>> $models */
    private function runIndex(FaceIndexer $indexer, CompreFaceClient $client, array $models, int $limit): int
    {
        $indexed = 0;
        $rejected = 0;
        $skipped = 0;
        $alreadyIndexed = 0;
        $pending = 0;
        $visited = 0;
        $removed = 0;
        $durations = [];
        $started = hrtime(true);
        $dryRun = (bool) $this->option('dry-run');
        $total = 0;
        foreach ($models as $model) {
            $count = $model::query()->published()->whereNotNull('photo_path')->count();
            $total += $count;
            $this->line(($model === PersonRecord::class ? 'Desaparecidos' : 'Solicitudes').": {$count} fichas públicas con fotografía.");
        }
        if ($dryRun) {
            $this->components->info('Revisión sin procesamiento: no se indexará ni retirará ningún rostro.');
        } elseif ($this->option('report')) {
            $path = Storage::disk('local')->path('face-index/'.Str::uuid().'.jsonl');
            $this->reportLogger = Log::build([
                'driver' => 'monolog',
                'handler' => StreamHandler::class,
                'handler_with' => ['stream' => $path],
                'formatter' => JsonFormatter::class,
                'level' => 'info',
            ]);
            $this->recordEvent('started', ['catalog' => $this->option('catalog'), 'total' => $total]);
            $this->line('Informe privado: '.$path);
        }

        try {
            $removed = ! $dryRun && $this->option('prune') ? $indexer->prune() : 0;
            $known = array_fill_keys($client->subjects(), true);
            foreach ($models as $model) {
                foreach ($model::query()->published()->whereNotNull('photo_path')->lazyById(200) as $person) {
                    if (! $dryRun && $limit > 0 && $indexed >= $limit) {
                        break 2;
                    }
                    $visited++;
                    $subject = FaceIndexer::subject($person);
                    if (! $this->option('refresh') && isset($known[$subject])) {
                        $alreadyIndexed++;
                        $this->recordEvent('already_indexed', ['subject' => $subject]);
                    } elseif ($dryRun) {
                        if ($person->hasPhoto() && Storage::disk(FaceIndexer::disk($person))->exists($person->photo_path)) {
                            $pending++;
                        } else {
                            $skipped++;
                        }
                    } else {
                        $imageStarted = hrtime(true);
                        try {
                            $saved = $indexer->sync($person instanceof PersonRecord ? 'record' : 'request', $person->id);
                            $duration = (hrtime(true) - $imageStarted) / 1_000_000;
                            if ($saved) {
                                $indexed++;
                                $durations[] = $duration;
                                if ($this->option('timings')) {
                                    $reference = $person instanceof PersonRecord ? $person->folio : $person->reference();
                                    $this->line(sprintf('ID %d | %s | %.2f ms', $person->id, $reference, $duration));
                                }
                            } else {
                                $skipped++;
                            }
                            $this->recordEvent($saved ? 'indexed' : 'missing_or_unpublished', ['subject' => $subject, 'duration_ms' => round($duration, 2)]);
                        } catch (ValidationException $exception) {
                            $rejected++;
                            $this->recordEvent('rejected', ['subject' => $subject, 'duration_ms' => round((hrtime(true) - $imageStarted) / 1_000_000, 2)]);
                            $this->components->warn($subject.': fotografía sin un único rostro válido.');
                        }
                    }
                    if ($this->option('progress') && $visited % 100 === 0) {
                        $this->line("Avance: {$visited}/{$total}. Indexados: {$indexed}. Ya registrados: {$alreadyIndexed}. Rechazados: {$rejected}. Sin archivo: {$skipped}.");
                    }
                }
            }
        } catch (ConnectionException|RequestException $exception) {
            $this->recordEvent('interrupted', [
                'indexed' => $indexed, 'rejected' => $rejected, 'skipped' => $skipped,
                'visited' => $visited, 'elapsed_seconds' => round((hrtime(true) - $started) / 1_000_000_000, 2),
            ]);
            $this->components->error('CompreFace no respondió correctamente. Comprueba el servicio y la clave API.');
            $this->line('Puedes repetir el mismo comando sin --refresh: los rostros guardados se omitirán.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->components->info("Revisión: {$visited} fichas. Ya indexadas: {$alreadyIndexed}. Pendientes con archivo: {$pending}. Sin archivo: {$skipped}. Ningún rostro modificado.");

            return self::SUCCESS;
        }

        $this->components->info("Rostros indexados: {$indexed}. Retirados: {$removed}. Fotos rechazadas: {$rejected}. Sin archivo: {$skipped}.");
        $this->line("Ya indexados omitidos: {$alreadyIndexed}. Revisadas: {$visited}/{$total}.");
        $this->recordEvent('finished', [
            'indexed' => $indexed, 'removed' => $removed, 'rejected' => $rejected,
            'skipped' => $skipped, 'already_indexed' => $alreadyIndexed,
            'visited' => $visited, 'total' => $total,
            'elapsed_seconds' => round((hrtime(true) - $started) / 1_000_000_000, 2),
        ]);
        if ($this->option('timings') && $durations !== []) {
            sort($durations);
            $middle = intdiv(count($durations), 2);
            $median = count($durations) % 2 === 0 ? ($durations[$middle - 1] + $durations[$middle]) / 2 : $durations[$middle];
            $this->line(sprintf(
                'Indexación completa por imagen: promedio %.2f ms; mediana %.2f ms; mínimo %.2f ms; máximo %.2f ms. Lote: %.2f s.',
                array_sum($durations) / count($durations), $median, min($durations), max($durations), (hrtime(true) - $started) / 1_000_000_000,
            ));
        }

        return ($limit > 0 && $indexed < $limit) || ($limit === 0 && ($rejected > 0 || $skipped > 0)) ? self::FAILURE : self::SUCCESS;
    }

    /** @param array<string, int|float|string> $context */
    private function recordEvent(string $event, array $context): void
    {
        $this->reportLogger?->info($event, $context);
    }
}
