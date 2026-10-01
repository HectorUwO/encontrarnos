<?php

namespace App\Console\Commands;

use App\Models\PersonRecord;
use App\Models\PersonRequest;
use App\Services\PhotoSearch\CompreFaceClient;
use App\Services\PhotoSearch\FaceIndexer;
use App\Services\PhotoSearch\FacePhotoVariants;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use RuntimeException;
use SplFileObject;

#[Signature('faces:recover {report : Nombre del informe JSONL de una indexación terminada} {--limit=0 : Máximo de fotos rechazadas revisadas, 0 sin límite} {--progress : Muestra avance cada 25 fotografías}')]
#[Description('Reintenta únicamente fotos rechazadas sin cambiar originales ni elegir rostros de grupos')]
class RecoverRejectedFaces extends Command
{
    public function handle(CompreFaceClient $client, FacePhotoVariants $variants): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if (! $client->isConfigured() || $limit === false || $limit < 0) {
            $this->components->error('Configura CompreFace y usa un límite entero no negativo.');

            return self::FAILURE;
        }
        try {
            $subjects = $this->rejectedSubjects((string) $this->argument('report'));
        } catch (RuntimeException|JsonException $exception) {
            $this->components->error('Usa el nombre de un informe privado válido cuya indexación haya terminado.');

            return self::FAILURE;
        }
        $lock = Cache::lock('face-index:catalog', 86400);
        if (! $lock->get()) {
            $this->components->error('Ya hay una indexación de rostros en ejecución.');

            return self::FAILURE;
        }
        $started = hrtime(true);
        $counts = ['indexed' => 0, 'no_face' => 0, 'multiple_faces' => 0, 'stale_or_missing' => 0, 'already_indexed' => 0];
        $visited = 0;
        $path = Storage::disk('local')->path('face-index/recovery-'.Str::uuid().'.jsonl');
        $logger = Log::build([
            'driver' => 'monolog', 'handler' => StreamHandler::class,
            'handler_with' => ['stream' => $path], 'formatter' => JsonFormatter::class, 'level' => 'info',
        ]);
        try {
            $logger->info('started', ['source_report' => $this->argument('report'), 'total' => count($subjects), 'det_prob_threshold' => 0.8]);
            $this->line('Informe privado: '.$path);
            $known = array_fill_keys($client->subjects(), true);
            foreach ($subjects as $subject) {
                if (isset($known[$subject])) {
                    $counts['already_indexed']++;

                    continue;
                }
                if ($limit > 0 && $visited >= $limit) {
                    break;
                }
                preg_match('/^(record|request)-([1-9][0-9]*)-[a-f0-9]{16}$/', $subject, $parts);
                $imageStarted = hrtime(true);
                $result = Cache::lock('face-index:'.$parts[1].':'.$parts[2], 120)->block(3,
                    fn (): array => $this->recover($client, $variants, $parts[1], (int) $parts[2], $subject));
                $visited++;
                $counts[$result['status']]++;
                $logger->info($result['status'], $result + ['subject' => $subject, 'duration_ms' => round((hrtime(true) - $imageStarted) / 1_000_000, 2)]);
                if ($this->option('progress') && $visited % 25 === 0) {
                    $this->line("Revisión: {$visited}/".count($subjects).". Recuperadas: {$counts['indexed']}. Sin rostro: {$counts['no_face']}. Varios rostros: {$counts['multiple_faces']}.");
                }
            }
            $logger->info('finished', $counts + ['visited' => $visited, 'total' => count($subjects), 'elapsed_seconds' => round((hrtime(true) - $started) / 1_000_000_000, 2)]);
            $this->components->info("Recuperadas: {$counts['indexed']}. Sin rostro: {$counts['no_face']}. Varios rostros: {$counts['multiple_faces']}. Cambiadas o sin archivo: {$counts['stale_or_missing']}. Ya indexadas: {$counts['already_indexed']}.");

            return self::SUCCESS;
        } catch (ConnectionException|RequestException $exception) {
            $logger->info('interrupted', $counts + ['visited' => $visited]);
            $this->components->error('CompreFace no respondió correctamente. Repite el comando: los rostros guardados se omitirán.');

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    /** @return list<string> */
    private function rejectedSubjects(string $report): array
    {
        if (preg_match('/^[a-f0-9-]{36}\.jsonl$/', $report) !== 1 || ! Storage::disk('local')->exists('face-index/'.$report)) {
            throw new RuntimeException('Invalid report');
        }
        $subjects = [];
        $lastEvent = null;
        foreach (new SplFileObject(Storage::disk('local')->path('face-index/'.$report)) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $event = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($event)) {
                throw new RuntimeException('Invalid event');
            }
            $lastEvent = $event['message'] ?? null;
            if ($lastEvent === 'rejected') {
                $subject = $event['context']['subject'] ?? null;
                if (! is_string($subject) || preg_match('/^(record|request)-([1-9][0-9]*)-[a-f0-9]{16}$/', $subject) !== 1) {
                    throw new RuntimeException('Invalid subject');
                }
                $subjects[$subject] = true;
            }
        }
        if ($lastEvent !== 'finished') {
            throw new RuntimeException('Unfinished report');
        }

        return array_keys($subjects);
    }

    /** @return array{status: string, variant?: string} */
    private function recover(CompreFaceClient $client, FacePhotoVariants $variants, string $type, int $id, string $subject): array
    {
        $person = ($type === 'record' ? PersonRecord::class : PersonRequest::class)::query()->published()->find($id);
        if ($person === null || ! $person->hasPhoto() || FaceIndexer::subject($person) !== $subject) {
            return ['status' => 'stale_or_missing'];
        }
        $disk = Storage::disk(FaceIndexer::disk($person));
        if (! $disk->exists($person->photo_path)) {
            return ['status' => 'stale_or_missing'];
        }
        $contents = $disk->get($person->photo_path);
        $code = $this->attempt($client, $subject, $contents, basename($person->photo_path));
        if ($code === 0) {
            return ['status' => 'indexed', 'variant' => 'original'];
        }
        if ($code === 31) {
            return ['status' => 'multiple_faces'];
        }
        foreach ($variants->make($contents) as $variant => $photo) {
            $code = $this->attempt($client, $subject, $photo, 'recovery.jpg');
            if ($code === 0) {
                return ['status' => 'indexed', 'variant' => $variant];
            }
            if ($code === 31) {
                return ['status' => 'multiple_faces'];
            }
        }

        return ['status' => 'no_face'];
    }

    private function attempt(CompreFaceClient $client, string $subject, string $contents, string $filename): int
    {
        $response = $client->request()->attach('file', $contents, $filename)
            ->post('/faces?'.http_build_query(['subject' => $subject, 'det_prob_threshold' => 0.8]));
        $code = (int) $response->json('code');
        if ($response->status() === 400 && in_array($code, [28, 31], true)) {
            return $code;
        }
        $response->throw();

        return 0;
    }
}
