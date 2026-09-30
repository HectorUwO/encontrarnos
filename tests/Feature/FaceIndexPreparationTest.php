<?php

namespace Tests\Feature;

use App\Models\PersonRecord;
use App\Models\PersonRequest;
use App\Services\PhotoSearch\FaceIndexer;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FaceIndexPreparationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_preflight_only_reads_subjects_and_checks_published_photos_even_with_prune_and_report_flags(): void
    {
        Storage::fake('record_photos');
        Storage::fake('local');
        $known = PersonRecord::factory()->withPhoto()->create();
        $pending = PersonRecord::factory()->withPhoto()->create();
        PersonRecord::factory()->withPhoto()->create();
        PersonRecord::factory()->unpublished()->withPhoto()->create();
        $request = PersonRequest::factory()->approved()->withPhoto()->create();
        PersonRequest::factory()->approved()->withPhoto()->create(['closed_at' => now()]);
        Storage::disk('record_photos')->put($pending->photo_path, 'photo');
        Storage::disk('local')->put($request->photo_path, 'photo');
        $this->enable();
        Http::fake(['http://compreface.test/api/v1/recognition/subjects' => Http::response([
            'subjects' => [FaceIndexer::subject($known)],
        ])]);

        $this->artisan('faces:index', ['--dry-run' => true, '--prune' => true, '--report' => true])
            ->expectsOutputToContain('Revisión: 4 fichas. Ya indexadas: 1. Pendientes con archivo: 2. Sin archivo: 1. Ningún rostro modificado.')
            ->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET');
        $this->assertSame([], Storage::disk('local')->allFiles('face-index'));
    }

    public function test_resume_skips_saved_faces_and_writes_private_per_image_and_summary_reports(): void
    {
        Storage::fake('record_photos');
        Storage::fake('local');
        $known = PersonRecord::factory()->withPhoto()->create();
        $pending = PersonRecord::factory()->withPhoto()->create();
        Storage::disk('record_photos')->put($pending->photo_path, UploadedFile::fake()->image('face.jpg')->getContent());
        $this->enable();
        Http::fake([
            'http://compreface.test/api/v1/recognition/subjects' => Http::response(['subjects' => [FaceIndexer::subject($known)]]),
            'http://compreface.test/api/v1/recognition/subjects/*' => Http::response([], 404),
            'http://compreface.test/api/v1/recognition/faces*' => Http::response(['image_id' => 'saved'], 201),
        ]);

        $this->artisan('faces:index', ['--catalog' => 'records', '--report' => true])
            ->expectsOutputToContain('Ya indexados omitidos: 1. Revisadas: 2/2.')
            ->assertSuccessful();

        $files = Storage::disk('local')->allFiles('face-index');
        $this->assertCount(1, $files);
        $events = array_map(fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
            explode("\n", trim(Storage::disk('local')->get($files[0]))));
        $this->assertSame(['started', 'already_indexed', 'indexed', 'finished'], array_column($events, 'message'));
        $this->assertSame(FaceIndexer::subject($pending), $events[2]['context']['subject']);
        $this->assertArrayHasKey('duration_ms', $events[2]['context']);
        $this->assertSame(1, $events[3]['context']['indexed']);
        Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET'
            && str_contains($request->url(), FaceIndexer::subject($known)));
        Http::assertSentCount(3);
    }

    public function test_service_failure_records_partial_progress_and_releases_the_run_lock(): void
    {
        Storage::fake('record_photos');
        Storage::fake('local');
        $person = PersonRecord::factory()->withPhoto()->create();
        Storage::disk('record_photos')->put($person->photo_path, 'photo');
        $this->enable();
        Http::fake([
            'http://compreface.test/api/v1/recognition/subjects' => Http::response(['subjects' => []]),
            'http://compreface.test/api/v1/recognition/subjects/*' => Http::response([], 404),
            'http://compreface.test/api/v1/recognition/faces*' => Http::failedConnection(),
        ]);

        $this->artisan('faces:index', ['--report' => true])
            ->expectsOutputToContain('Puedes repetir el mismo comando sin --refresh')
            ->assertFailed();

        $files = Storage::disk('local')->allFiles('face-index');
        $events = array_map(fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
            explode("\n", trim(Storage::disk('local')->get($files[0]))));
        $this->assertSame('interrupted', $events[1]['message']);
        $this->assertSame(0, $events[1]['context']['indexed']);
        $lock = Cache::lock('face-index:catalog', 60);
        $this->assertTrue($lock->get());
        $lock->release();
        Http::assertSentCount(3);
    }

    public function test_another_run_cannot_start_while_the_catalog_lock_is_held(): void
    {
        $this->enable();
        $lock = Cache::lock('face-index:catalog', 60);
        $lock->get();

        try {
            $this->artisan('faces:index')
                ->expectsOutputToContain('Ya hay una indexación de rostros en ejecución.')
                ->assertFailed();
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    public function test_automatic_indexing_waits_for_explicit_configuration(): void
    {
        $this->enable();
        config(['services.compreface.auto_index' => false]);
        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(fn ($event): bool => str_contains($event->command ?? '', 'faces:index'));

        $this->assertCount(1, $events);
        $this->assertFalse($events->first()->filtersPass($this->app));
        config(['services.compreface.auto_index' => true]);
        $this->assertTrue($events->first()->filtersPass($this->app));
        Http::assertNothingSent();
    }

    private function enable(): void
    {
        config([
            'services.compreface.enabled' => true,
            'services.compreface.host' => 'http://compreface.test',
            'services.compreface.key' => 'server-only-key',
        ]);
    }
}
