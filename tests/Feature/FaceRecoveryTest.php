<?php

namespace Tests\Feature;

use App\Models\PersonRecord;
use App\Models\PersonRequest;
use App\Services\PhotoSearch\FaceIndexer;
use App\Services\PhotoSearch\FacePhotoVariants;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FaceRecoveryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const REPORT = '00000000-0000-4000-8000-000000000001.jsonl';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config([
            'services.compreface.enabled' => false,
            'services.compreface.host' => 'http://compreface.test',
            'services.compreface.key' => 'server-only-key',
        ]);
    }

    public function test_recovers_a_scaled_photo_without_overwriting_originals_or_deleting_saved_faces(): void
    {
        Storage::fake('local');
        Storage::fake('record_photos');
        $person = PersonRecord::factory()->withPhoto()->create();
        $original = UploadedFile::fake()->image('face.jpg', 100, 200)->getContent();
        Storage::disk('record_photos')->put($person->photo_path, $original);
        $this->report([FaceIndexer::subject($person)]);
        Http::fake([
            'http://compreface.test/api/v1/recognition/subjects' => Http::response(['subjects' => []]),
            'http://compreface.test/api/v1/recognition/faces*' => Http::sequence()->push(['code' => 28], 400)->push(['image_id' => 'saved'], 201),
        ]);

        $this->artisan('faces:recover', ['report' => self::REPORT])->expectsOutputToContain('Recuperadas: 1. Sin rostro: 0.')->assertSuccessful();

        $this->assertSame($original, Storage::disk('record_photos')->get($person->photo_path));
        $events = $this->recoveryEvents();
        $this->assertSame('scaled_640', $events[1]['context']['variant']);
        $this->assertSame(FaceIndexer::subject($person), $events[1]['context']['subject']);
        $this->assertSame(1, $events[2]['context']['indexed']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'det_prob_threshold=0.8'));
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'DELETE');
        Http::assertSentCount(3);
    }

    public function test_stops_on_multiple_faces_without_cropping_or_trying_other_orientations(): void
    {
        Storage::fake('local');
        Storage::fake('record_photos');
        $person = PersonRecord::factory()->withPhoto()->create();
        Storage::disk('record_photos')->put($person->photo_path, UploadedFile::fake()->image('group.jpg')->getContent());
        $this->report([FaceIndexer::subject($person)]);
        Http::fake([
            'http://compreface.test/api/v1/recognition/subjects' => Http::response(['subjects' => []]),
            'http://compreface.test/api/v1/recognition/faces*' => Http::response(['code' => 31], 400),
        ]);

        $this->artisan('faces:recover', ['report' => self::REPORT])->expectsOutputToContain('Varios rostros: 1.')->assertSuccessful();

        Http::assertSentCount(2);
        $this->assertSame('multiple_faces', $this->recoveryEvents()[1]['message']);
    }

    public function test_records_unrecoverable_photos_after_trying_only_bounded_variants(): void
    {
        Storage::fake('local');
        Storage::fake('record_photos');
        $person = PersonRecord::factory()->withPhoto()->create();
        Storage::disk('record_photos')->put($person->photo_path, UploadedFile::fake()->image('face.jpg')->getContent());
        $this->report([FaceIndexer::subject($person)]);
        Http::fake([
            'http://compreface.test/api/v1/recognition/subjects' => Http::response(['subjects' => []]),
            'http://compreface.test/api/v1/recognition/faces*' => Http::response(['code' => 28], 400),
        ]);

        $this->artisan('faces:recover', ['report' => self::REPORT])->expectsOutputToContain('Sin rostro: 1.')->assertSuccessful();

        Http::assertSentCount(6);
        $this->assertSame('no_face', $this->recoveryEvents()[1]['message']);
    }

    public function test_skips_indexed_changed_and_unpublished_subjects_and_only_retries_unique_rejections(): void
    {
        Storage::fake('local');
        Storage::fake('record_photos');
        $known = PersonRecord::factory()->withPhoto()->create();
        $unpublished = PersonRecord::factory()->unpublished()->withPhoto()->create();
        $changed = PersonRecord::factory()->withPhoto()->create();
        $pending = PersonRequest::factory()->approved()->withPhoto()->create();
        Storage::disk('local')->put($pending->photo_path, 'photo');
        $this->report([FaceIndexer::subject($known), FaceIndexer::subject($unpublished), FaceIndexer::subject($changed, 'old.jpg'), FaceIndexer::subject($pending), FaceIndexer::subject($pending)]);
        Http::fake([
            'http://compreface.test/api/v1/recognition/subjects' => Http::response(['subjects' => [FaceIndexer::subject($known)]]),
            'http://compreface.test/api/v1/recognition/faces*' => Http::response(['image_id' => 'saved'], 201),
        ]);

        $this->artisan('faces:recover', ['report' => self::REPORT])->expectsOutputToContain('Cambiadas o sin archivo: 2. Ya indexadas: 1.')->assertSuccessful();

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), FaceIndexer::subject($pending)));
    }

    public function test_refuses_unfinished_or_outside_reports_without_sending_requests(): void
    {
        Storage::fake('local');
        $this->report([], false);

        $this->artisan('faces:recover', ['report' => self::REPORT])->assertFailed();
        $this->artisan('faces:recover', ['report' => '../'.self::REPORT])->assertFailed();

        Http::assertNothingSent();
        $this->assertCount(1, Storage::disk('local')->allFiles('face-index'));
    }

    public function test_service_failure_keeps_partial_report_and_releases_the_catalog_lock(): void
    {
        Storage::fake('local');
        Storage::fake('record_photos');
        $person = PersonRecord::factory()->withPhoto()->create();
        Storage::disk('record_photos')->put($person->photo_path, 'photo');
        $this->report([FaceIndexer::subject($person)]);
        Http::fake([
            'http://compreface.test/api/v1/recognition/subjects' => Http::response(['subjects' => []]),
            'http://compreface.test/api/v1/recognition/faces*' => Http::failedConnection(),
        ]);

        $this->artisan('faces:recover', ['report' => self::REPORT])->assertFailed();

        $this->assertSame('interrupted', $this->recoveryEvents()[1]['message']);
        $lock = Cache::lock('face-index:catalog', 60);
        $this->assertTrue($lock->get());
        $lock->release();
        Http::assertSentCount(2);
    }

    public function test_refuses_recovery_while_a_full_indexing_is_running(): void
    {
        Storage::fake('local');
        $this->report([]);
        $lock = Cache::lock('face-index:catalog', 60);
        $lock->get();

        try {
            $this->artisan('faces:recover', ['report' => self::REPORT])->assertFailed();
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    public function test_variants_keep_all_pixels_in_frame_and_swap_dimensions_for_quarter_turns(): void
    {
        $original = UploadedFile::fake()->image('photo.jpg', 100, 200)->getContent();

        $variants = iterator_to_array((new FacePhotoVariants)->make($original));

        $this->assertSame(['scaled_640', 'rotated_90', 'rotated_180', 'rotated_270'], array_keys($variants));
        $this->assertSame([320, 640], array_slice(getimagesizefromstring($variants['scaled_640']), 0, 2));
        $this->assertSame([640, 320], array_slice(getimagesizefromstring($variants['rotated_90']), 0, 2));
        $this->assertSame([320, 640], array_slice(getimagesizefromstring($variants['rotated_180']), 0, 2));
        $this->assertSame([640, 320], array_slice(getimagesizefromstring($variants['rotated_270']), 0, 2));
        $this->assertSame([], iterator_to_array((new FacePhotoVariants)->make('not an image')));
    }

    /** @param list<string> $subjects */
    private function report(array $subjects, bool $finished = true): void
    {
        config(['services.compreface.enabled' => true]);
        $events = [['message' => 'started', 'context' => []]];
        foreach ($subjects as $subject) {
            $events[] = ['message' => 'rejected', 'context' => ['subject' => $subject]];
        }
        if ($finished) {
            $events[] = ['message' => 'finished', 'context' => []];
        }
        Storage::disk('local')->put('face-index/'.self::REPORT, implode("\n", array_map(fn (array $event): string => json_encode($event, JSON_THROW_ON_ERROR), $events))."\n");
    }

    /** @return list<array<string, mixed>> */
    private function recoveryEvents(): array
    {
        $files = Storage::disk('local')->files('face-index');
        $path = array_values(array_filter($files, fn (string $path): bool => str_contains($path, 'recovery-')))[0];

        return array_map(fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR), explode("\n", trim(Storage::disk('local')->get($path))));
    }
}
