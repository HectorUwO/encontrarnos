<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[Signature('records:backup-rnpdno {--fresh : Borra y vuelve a copiar las tablas ya terminadas} {--table=* : Solo estas tablas}')]
#[Description('Copia completa, tal cual, de la base SQLite del colector RNPDNO a la base MySQL «rnpdno» y verifica filas y bytes')]
class BackupRnpdnoToMysql extends Command
{
    private const PACKET_BUDGET = 48_000_000;

    /**
     * Tablas del colector. «key» son las columnas de la llave primaria (para leer por
     * lotes sin OFFSET), «text» las columnas de texto cuyo tamaño se verifica y
     * «batch» las filas por lote (pequeño donde hay JSON y fotos en base64).
     *
     * @var array<string, array{ddl: string, key: list<string>, text: list<string>, batch: int}>
     */
    private const TABLES = [
        'runs' => [
            'ddl' => 'id BIGINT PRIMARY KEY, started_at VARCHAR(40) NOT NULL, finished_at VARCHAR(40), mode VARCHAR(40) NOT NULL, states_json LONGTEXT NOT NULL, sample_limit BIGINT, insecure_api TINYINT NOT NULL, status VARCHAR(40) NOT NULL, error LONGTEXT',
            'key' => ['id'], 'text' => ['states_json', 'error'], 'batch' => 100,
        ],
        'states' => [
            'ddl' => 'run_id BIGINT NOT NULL, state VARCHAR(80) NOT NULL, total_before BIGINT, total_after BIGINT, catalog_json LONGTEXT, status VARCHAR(40) NOT NULL, error LONGTEXT, PRIMARY KEY (run_id, state)',
            'key' => ['run_id', 'state'], 'text' => ['catalog_json', 'error'], 'batch' => 20,
        ],
        'partitions' => [
            'ddl' => 'id BIGINT PRIMARY KEY, run_id BIGINT NOT NULL, state VARCHAR(80) NOT NULL, municipality VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, expected_count BIGINT, requested_rows BIGINT, received_count BIGINT, response_json LONGTEXT, status VARCHAR(40) NOT NULL, error LONGTEXT, UNIQUE KEY partitions_run_state_municipality (run_id, state, municipality)',
            'key' => ['id'], 'text' => ['response_json', 'error'], 'batch' => 50,
        ],
        'images' => [
            'ddl' => 'sha256 CHAR(64) PRIMARY KEY, path VARCHAR(255) NOT NULL, format VARCHAR(20) NOT NULL, width BIGINT NOT NULL, height BIGINT NOT NULL, bytes BIGINT NOT NULL',
            'key' => ['sha256'], 'text' => ['path', 'format'], 'batch' => 2000,
        ],
        'placeholders' => [
            'ddl' => 'sha256 CHAR(64) PRIMARY KEY, label VARCHAR(255) NOT NULL, source_url TEXT, registered_at VARCHAR(40) NOT NULL',
            'key' => ['sha256'], 'text' => ['label', 'source_url'], 'batch' => 500,
        ],
        'reports' => [
            'ddl' => 'id BIGINT PRIMARY KEY, run_id BIGINT NOT NULL, victim_id VARCHAR(80) NOT NULL, report_id BIGINT NOT NULL, agency_id BIGINT NOT NULL, raw_response_json LONGTEXT, data_json LONGTEXT, source_path VARCHAR(500), name VARCHAR(500), state VARCHAR(120), municipality VARCHAR(255), event_date_raw VARCHAR(120), status VARCHAR(40) NOT NULL, image_status VARCHAR(40) NOT NULL, image_sha256 CHAR(64), declared_mime VARCHAR(120), error LONGTEXT, captured_at VARCHAR(40), UNIQUE KEY reports_run_victim_report_agency (run_id, victim_id, report_id, agency_id), KEY reports_dashboard_status (run_id, status, image_status), KEY reports_image (image_sha256)',
            'key' => ['id'], 'text' => ['victim_id', 'raw_response_json', 'data_json', 'source_path', 'name', 'state', 'municipality', 'event_date_raw', 'status', 'image_status', 'image_sha256', 'declared_mime', 'error', 'captured_at'], 'batch' => 25,
        ],
        'observations' => [
            'ddl' => 'id BIGINT PRIMARY KEY, partition_id BIGINT NOT NULL, position BIGINT NOT NULL, raw_json LONGTEXT NOT NULL, confidential TINYINT NOT NULL, status VARCHAR(40) NOT NULL, error LONGTEXT, linked_response_json LONGTEXT, captured_at VARCHAR(40) NOT NULL, UNIQUE KEY observations_partition_position (partition_id, position)',
            'key' => ['id'], 'text' => ['raw_json', 'status', 'error', 'linked_response_json', 'captured_at'], 'batch' => 100,
        ],
        'observation_reports' => [
            'ddl' => 'observation_id BIGINT NOT NULL, report_id BIGINT NOT NULL, PRIMARY KEY (observation_id, report_id), KEY observation_reports_report (report_id)',
            'key' => ['observation_id', 'report_id'], 'text' => [], 'batch' => 5000,
        ],
        'fields' => [
            'ddl' => 'report_id BIGINT NOT NULL, position BIGINT NOT NULL, `key` VARCHAR(190) NOT NULL, value_json LONGTEXT NOT NULL, PRIMARY KEY (report_id, position), KEY fields_by_key (`key`, report_id)',
            'key' => ['report_id', 'position'], 'text' => ['key', 'value_json'], 'batch' => 4000,
        ],
        'api_events' => [
            'ddl' => 'id BIGINT PRIMARY KEY, run_id BIGINT NOT NULL, at VARCHAR(40) NOT NULL, action VARCHAR(120) NOT NULL, params_json LONGTEXT NOT NULL, body_json LONGTEXT NOT NULL, http_status BIGINT, seconds DOUBLE NOT NULL, response_json LONGTEXT, error LONGTEXT',
            'key' => ['id'], 'text' => ['at', 'action', 'params_json', 'body_json', 'response_json', 'error'], 'batch' => 25,
        ],
        'anchor_sets' => [
            'ddl' => 'run_id BIGINT NOT NULL, state VARCHAR(80) NOT NULL, created_at VARCHAR(40) NOT NULL, coverage_complete TINYINT NOT NULL, ordering_verified TINYINT NOT NULL, usable_for_incremental TINYINT NOT NULL, PRIMARY KEY (run_id, state)',
            'key' => ['run_id', 'state'], 'text' => ['created_at'], 'batch' => 100,
        ],
        'anchors' => [
            'ddl' => '`run_id` BIGINT NOT NULL, state VARCHAR(80) NOT NULL, `rank` BIGINT NOT NULL, observation_id BIGINT NOT NULL, victim_id VARCHAR(80) NOT NULL, report_id BIGINT NOT NULL, agency_id BIGINT NOT NULL, linked_id VARCHAR(80), source_capture_date VARCHAR(40), PRIMARY KEY (run_id, state, `rank`)',
            'key' => ['run_id', 'state', 'rank'], 'text' => ['victim_id', 'linked_id', 'source_capture_date'], 'batch' => 500,
        ],
        'incremental_checks' => [
            'ddl' => 'partition_id BIGINT PRIMARY KEY, checked_at VARCHAR(40) NOT NULL',
            'key' => ['partition_id'], 'text' => ['checked_at'], 'batch' => 5000,
        ],
    ];

    public function handle(): int
    {
        ini_set('memory_limit', '3G');
        $source = DB::connection('rnpdno');
        $target = DB::connection('rnpdno_backup');
        try {
            $source->selectOne('select 1 from runs limit 1');
            $target->statement('create table if not exists _backup_progress (table_name varchar(64) primary key, row_count bigint not null, bytes bigint not null, finished_at datetime not null)');
        } catch (\Throwable $exception) {
            $this->components->error('No se pudo abrir la base del colector o la base «rnpdno» de MySQL: '.$exception->getMessage());

            return self::FAILURE;
        }
        $only = array_filter((array) $this->option('table'));
        $failed = false;
        foreach (self::TABLES as $name => $spec) {
            if ($only !== [] && ! in_array($name, $only, true)) {
                continue;
            }
            $done = $target->table('_backup_progress')->where('table_name', $name)->exists();
            if ($done && ! $this->option('fresh')) {
                $this->line("{$name}: ya copiada, se omite.");

                continue;
            }
            try {
                $this->copyTable($source, $target, $name, $spec);
            } catch (\Throwable $exception) {
                $failed = true;
                $this->components->error("{$name}: ".$exception->getMessage());
            }
        }
        if ($target->table('_backup_progress')->whereIn('table_name', ['runs', 'partitions', 'observations'])->count() === 3) {
            $this->createView($target);
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** @param array{ddl: string, key: list<string>, text: list<string>, batch: int} $spec */
    private function copyTable(Connection $source, Connection $target, string $name, array $spec): void
    {
        $target->statement("drop table if exists `{$name}`");
        $target->table('_backup_progress')->where('table_name', $name)->delete();
        $target->statement("create table `{$name}` ({$spec['ddl']}) engine=InnoDB default charset=utf8mb4");
        $expected = (int) $source->table($name)->count();
        $this->line("{$name}: {$expected} filas...");
        $rows = 0;
        $bytes = 0;
        $last = null;
        $started = microtime(true);
        $keys = $spec['key'];
        $columns = array_map(fn (string $c): string => '"'.$c.'"', $keys);
        $tuple = '('.implode(', ', $columns).')';
        while (true) {
            $query = $source->table($name);
            if ($last !== null) {
                $marks = '('.implode(', ', array_fill(0, count($keys), '?')).')';
                $query->whereRaw("{$tuple} > {$marks}", $last);
            }
            foreach ($keys as $key) {
                $query->orderBy($key);
            }
            $batch = $query->limit($spec['batch'])->get();
            if ($batch->isEmpty()) {
                break;
            }
            $chunk = [];
            $size = 0;
            foreach ($batch as $row) {
                $row = (array) $row;
                $rowSize = 0;
                foreach ($spec['text'] as $column) {
                    $rowSize += strlen((string) ($row[$column] ?? ''));
                }
                if ($chunk !== [] && $size + $rowSize > self::PACKET_BUDGET) {
                    $target->table($name)->insert($chunk);
                    $chunk = [];
                    $size = 0;
                }
                $chunk[] = $row;
                $size += $rowSize;
                $bytes += $rowSize;
                $rows++;
            }
            if ($chunk !== []) {
                $target->table($name)->insert($chunk);
            }
            $lastRow = (array) $batch->last();
            $last = array_map(fn (string $key) => $lastRow[$key], $keys);
            if ($rows % max($spec['batch'] * 40, 20000) < $spec['batch']) {
                $this->line(sprintf('  %s: %d/%d (%.0f s, %.1f GB)', $name, $rows, $expected, microtime(true) - $started, $bytes / 1e9));
            }
        }
        $copied = (int) $target->table($name)->count();
        $copiedBytes = 0;
        if ($spec['text'] !== []) {
            $sums = implode(' + ', array_map(fn (string $c): string => "coalesce(sum(length(`{$c}`)), 0)", $spec['text']));
            $copiedBytes = (int) $target->selectOne("select {$sums} as total from `{$name}`")->total;
        }
        if ($copied !== $expected || $rows !== $expected || $copiedBytes !== $bytes) {
            throw new RuntimeException("no coincide (origen {$expected} filas/{$bytes} bytes, destino {$copied} filas/{$copiedBytes} bytes)");
        }
        $target->table('_backup_progress')->insert(['table_name' => $name, 'row_count' => $rows, 'bytes' => $bytes, 'finished_at' => now()]);
        $this->components->info(sprintf('%s: %d filas y %.2f GB verificados en %.0f s.', $name, $rows, $bytes / 1e9, microtime(true) - $started));
    }

    private function createView(Connection $target): void
    {
        $pick = fn (string $key): string => "json_unquote(json_extract(o.raw_json, '\$.{$key}'))";
        $target->statement('create or replace view sample_rows as select r.id as run_id, p.state, p.municipality as partition_municipality, o.position, o.confidential, o.status, '
            .$pick('nombre').' as nombre, '.$pick('primerapellido').' as primerapellido, '.$pick('segundoapellido').' as segundoapellido, '
            .$pick('municipio').' as municipio, '.$pick('ffechahechos').' as fechahechos, '.$pick('dependenciaOrigen').' as dependencia, '
            .'o.id as observation_id, o.raw_json from observations o join partitions p on p.id = o.partition_id join runs r on r.id = p.run_id');
    }
}
