<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\PersonRequestStatus;
use App\Models\PersonRequest;
use App\Services\Photos\PhotoCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PersonRequestPhotoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('record_thumbnails');
    }

    private function jpeg(int $width, int $height): string
    {
        $image = UploadedFile::fake()->image('foto.jpg', $width, $height);

        return file_get_contents($image->getRealPath());
    }

    public function test_serves_the_photo_of_an_approved_request(): void
    {
        $request = PersonRequest::factory()->approved()->withPhoto()->create();
        Storage::put($request->photo_path, 'contenido-de-la-foto');

        $response = $this->get(route('requests.photo', $request));

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame('contenido-de-la-foto', $response->streamedContent());
    }

    public function test_keeps_the_photo_for_a_week_only_when_the_address_carries_its_current_version(): void
    {
        $request = PersonRequest::factory()->approved()->withPhoto()->create();
        Storage::put($request->photo_path, 'contenido-de-la-foto');

        $current = $this->get(route('requests.photo', [$request, 'v' => PhotoCache::version($request->photo_path)]));
        $stale = $this->get(route('requests.photo', [$request, 'v' => 'foto-vieja']));

        $this->assertStringContainsString('max-age=604800', $current->headers->get('Cache-Control'));
        $this->assertStringContainsString('immutable', $current->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=86400', $stale->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('immutable', $stale->headers->get('Cache-Control'));
    }

    public function test_serves_a_reduced_copy_of_a_heavy_upload_when_a_size_is_requested(): void
    {
        $request = PersonRequest::factory()->approved()->withPhoto()->create();
        $original = $this->jpeg(2400, 1800);
        Storage::put($request->photo_path, $original);

        $body = $this->get(route('requests.photo', [$request, 'size' => 'thumb']))->streamedContent();

        $this->assertSame([320, 240], array_slice(getimagesizefromstring($body), 0, 2));
        $this->assertLessThan(strlen($original), strlen($body));
        $this->assertSame($original, Storage::get($request->photo_path));
    }

    public function test_serves_the_original_when_it_cannot_be_reduced_or_the_size_is_unknown(): void
    {
        $request = PersonRequest::factory()->approved()->withPhoto()->create();
        Storage::put($request->photo_path, 'esto no es una imagen');

        $this->get(route('requests.photo', [$request, 'size' => 'thumb']))->assertOk();
        $this->assertSame(
            'esto no es una imagen',
            $this->get(route('requests.photo', [$request, 'size' => 'gigante']))->streamedContent(),
        );
    }

    #[DataProvider('unapprovedStatuses')]
    public function test_does_not_serve_the_photo_of_an_unapproved_request(PersonRequestStatus $status): void
    {
        $request = PersonRequest::factory()->withPhoto()->create(['status' => $status]);
        Storage::put($request->photo_path, $this->jpeg(800, 600));

        $this->get(route('requests.photo', $request))->assertNotFound();
        $this->get(route('requests.photo', [$request, 'size' => 'thumb']))->assertNotFound();
        $this->assertSame([], Storage::disk('record_thumbnails')->allFiles());
    }

    /**
     * @return array<string, array{PersonRequestStatus}>
     */
    public static function unapprovedStatuses(): array
    {
        return [
            'pending' => [PersonRequestStatus::Pending],
            'rejected' => [PersonRequestStatus::Rejected],
        ];
    }

    public function test_returns_404_when_the_request_has_no_photo(): void
    {
        $request = PersonRequest::factory()->approved()->create();

        $this->get(route('requests.photo', $request))->assertNotFound();
    }

    public function test_returns_404_when_the_photo_file_is_missing(): void
    {
        $request = PersonRequest::factory()->approved()->withPhoto()->create();

        $this->get(route('requests.photo', $request))->assertNotFound();
    }
}
