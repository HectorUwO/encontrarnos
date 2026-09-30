<?php

namespace App\Console\Commands;

use App\Models\PersonRecord;
use App\Services\Search\MeilisearchClient;
use App\Services\Search\RecordIndexer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

#[Signature('records:index {--fresh : Borra el índice y lo arma de nuevo} {--chunk=2000 : Fichas por lote}')]
#[Description('Sincroniza el índice de búsqueda (Meilisearch) con las fichas públicas')]
class IndexPersonRecords extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RecordIndexer $indexer, MeilisearchClient $client): int
    {
        if (! $client->configured()) {
            $this->components->warn('RECORDS_SEARCH no es «meilisearch»: se indexa igual, pero la búsqueda seguirá usando la base de datos.');
        }

        $bar = $this->output->createProgressBar(PersonRecord::query()->published()->count());
        $bar->start();

        try {
            $result = $indexer->sync(
                max(1, (int) $this->option('chunk')),
                (bool) $this->option('fresh'),
                fn (int $count) => $bar->advance($count),
            );
        } catch (ConnectionException $exception) {
            $this->newLine(2);
            $this->components->error("No se pudo conectar con Meilisearch en {$client->host()}. ¿Está encendido?");

            return self::FAILURE;
        } catch (RequestException|RuntimeException $exception) {
            $this->newLine(2);
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine(2);
        $this->components->twoColumnDetail('Fichas enviadas al índice', number_format($result['indexed']));
        $this->components->twoColumnDetail('Fichas retiradas del índice', number_format($result['removed']));
        $this->components->twoColumnDetail('Documentos en el índice', number_format($result['total']));

        return self::SUCCESS;
    }
}
