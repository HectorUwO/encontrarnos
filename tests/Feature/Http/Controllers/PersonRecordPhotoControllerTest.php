<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\PersonRecord;
use App\Services\Photos\PhotoCache;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PersonRecordPhotoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('record_photos');
        Storage::fake('record_thumbnails');
    }

    private function jpeg(int $width, int $height): string
    {
        $image = UploadedFile::fake()->image('foto.jpg', $width, $height);

        return file_get_contents($image->getRealPath());
    }

    private function recordWithPhoto(string $contents = 'contenido-de-la-foto'): PersonRecord
    {
        $record = PersonRecord::factory()->withPhoto()->create();
        Storage::disk('record_photos')->put($record->photo_path, $contents);

        return $record;
    }

    public function test_serves_the_photo_of_a_published_record_with_cache_headers(): void
    {
        $record = $this->recordWithPhoto();

        $response = $this->get(route('records.photo', $record));

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('max-age=86400', $response->headers->get('Cache-Control'));
        $this->assertSame('contenido-de-la-foto', $response->streamedContent());
    }

    public function test_keeps_the_photo_for_a_week_when_the_address_carries_its_current_version(): void
    {
        $record = $this->recordWithPhoto();

        $response = $this->get(route('records.photo', [$record, 'v' => PhotoCache::version($record->photo_path)]));

        $this->assertStringContainsString('max-age=604800', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('immutable', $response->headers->get('Cache-Control'));
    }

    public function test_keeps_the_photo_for_a_day_when_the_version_in_the_address_is_stale(): void
    {
        $record = $this->recordWithPhoto();

        $response = $this->get(route('records.photo', [$record, 'v' => 'foto-vieja']));

        $this->assertStringContainsString('max-age=86400', $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('immutable', $response->headers->get('Cache-Control'));
    }

    public function test_serves_a_reduced_copy_when_a_size_is_requested(): void
    {
        $original = $this->jpeg(1200, 900);
        $record = $this->recordWithPhoto($original);

        $response = $this->get(route('records.photo', [$record, 'size' => 'thumb']));

        $body = $response->streamedContent();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('max-age=86400', $response->headers->get('Cache-Control'));
        $this->assertSame([320, 240], array_slice(getimagesizefromstring($body), 0, 2));
        $this->assertLessThan(strlen($original), strlen($body));
        $this->assertSame($original, Storage::disk('record_photos')->get($record->photo_path));
    }

    public function test_serves_the_original_when_the_size_is_unknown(): void
    {
        $original = $this->jpeg(800, 600);
        $record = $this->recordWithPhoto($original);

        $response = $this->get(route('records.photo', [$record, 'size' => 'gigante']));

        $this->assertSame($original, $response->streamedContent());
        $this->assertSame([], Storage::disk('record_thumbnails')->allFiles());
    }

    public function test_serves_the_original_when_it_cannot_be_reduced(): void
    {
        $record = $this->recordWithPhoto('esto no es una imagen');

        $response = $this->get(route('records.photo', [$record, 'size' => 'thumb']));

        $response->assertOk();
        $this->assertSame('esto no es una imagen', $response->streamedContent());
    }

    public function test_limits_how_many_photos_a_client_can_request(): void
    {
        RateLimiter::for('photos', fn () => Limit::perMinute(2));
        $record = $this->recordWithPhoto();

        $this->get(route('records.photo', $record))->assertOk();
        $this->get(route('records.photo', $record))->assertOk();
        $this->get(route('records.photo', $record))->assertTooManyRequests();
    }

    public function test_returns_404_for_an_unpublished_record(): void
    {
        $record = PersonRecord::factory()->unpublished()->withPhoto()->create();
        Storage::disk('record_photos')->put($record->photo_path, $this->jpeg(800, 600));

        $this->get(route('records.photo', $record))->assertNotFound();
        $this->get(route('records.photo', [$record, 'size' => 'thumb']))->assertNotFound();
        $this->assertSame([], Storage::disk('record_thumbnails')->allFiles());
    }

    public function test_returns_404_when_the_record_has_no_photo(): void
    {
        $record = PersonRecord::factory()->create();

        $this->get(route('records.photo', $record))->assertNotFound();
    }

    public function test_returns_404_when_the_photo_file_is_missing(): void
    {
        $record = PersonRecord::factory()->withPhoto()->create();

        $this->get(route('records.photo', $record))->assertNotFound();
        $this->get(route('records.photo', [$record, 'size' => 'thumb']))->assertNotFound();
    }
}
