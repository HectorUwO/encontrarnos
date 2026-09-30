<?php

namespace Tests\Feature\Services\Photos;

use App\Enums\PhotoSize;
use App\Services\Photos\PhotoThumbnails;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoThumbnailsTest extends TestCase
{
    private const PATH = 'imagenes/ab/foto.jpg';

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

    /**
     * Agrega al JPEG un bloque EXIF con la orientación indicada, como lo hacen
     * las cámaras que giran la foto al mostrarla en vez de al guardarla.
     */
    private function jpegWithOrientation(int $width, int $height, int $orientation): string
    {
        $jpeg = $this->jpeg($width, $height);
        $exif = "Exif\0\0"."MM\0*\0\0\0\x08"."\0\x01"."\x01\x12\0\x03\0\0\0\x01".pack('n', $orientation)."\0\0"."\0\0\0\0";
        $afterJfif = 4 + unpack('n', substr($jpeg, 4, 2))[1];

        return substr($jpeg, 0, $afterJfif)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, $afterJfif);
    }

    private function store(string $contents, string $path = self::PATH, string $disk = 'record_photos'): void
    {
        Storage::disk($disk)->put($path, $contents);
    }

    private function thumbnail(PhotoSize $size = PhotoSize::Thumbnail, string $path = self::PATH, string $disk = 'record_photos'): ?string
    {
        return (new PhotoThumbnails)->pathFor($disk, $path, $size);
    }

    /**
     * @return array{0: int, 1: int, 2: string}
     */
    private function dimensionsOf(string $path): array
    {
        $info = getimagesizefromstring(Storage::disk('record_thumbnails')->get($path));

        return [$info[0], $info[1], $info['mime']];
    }

    public function test_creates_a_jpeg_no_wider_than_the_requested_size_keeping_the_proportions(): void
    {
        $this->store($this->jpeg(800, 600));

        $path = $this->thumbnail();

        $this->assertStringStartsWith('thumb/', $path);
        $this->assertStringEndsWith('.jpg', $path);
        $this->assertSame([320, 240, 'image/jpeg'], $this->dimensionsOf($path));
    }

    public function test_keeps_each_size_in_its_own_folder(): void
    {
        $this->store($this->jpeg(1200, 900));

        $thumbnail = $this->thumbnail(PhotoSize::Thumbnail);
        $medium = $this->thumbnail(PhotoSize::Medium);

        $this->assertNotSame($thumbnail, $medium);
        $this->assertSame([320, 240, 'image/jpeg'], $this->dimensionsOf($thumbnail));
        $this->assertSame([800, 600, 'image/jpeg'], $this->dimensionsOf($medium));
    }

    public function test_keeps_photos_with_the_same_path_on_different_disks_apart(): void
    {
        $this->store($this->jpeg(800, 600));
        Storage::fake('local');
        $this->store($this->jpeg(1000, 1000), disk: 'local');

        $fromRecords = $this->thumbnail(disk: 'record_photos');
        $fromRequests = $this->thumbnail(disk: 'local');

        $this->assertNotSame($fromRecords, $fromRequests);
        $this->assertSame([320, 240, 'image/jpeg'], $this->dimensionsOf($fromRecords));
        $this->assertSame([320, 320, 'image/jpeg'], $this->dimensionsOf($fromRequests));
    }

    public function test_keeps_a_jpeg_that_already_fits_exactly_as_it_is(): void
    {
        $original = $this->jpeg(200, 100);
        $this->store($original);

        $path = $this->thumbnail();

        $this->assertSame($original, Storage::disk('record_thumbnails')->get($path));
    }

    public function test_converts_other_formats_to_jpeg_without_enlarging_them(): void
    {
        $image = UploadedFile::fake()->image('foto.png', 200, 100);
        $this->store(file_get_contents($image->getRealPath()));

        $path = $this->thumbnail();

        $this->assertSame([200, 100, 'image/jpeg'], $this->dimensionsOf($path));
    }

    public function test_reuses_the_saved_thumbnail_instead_of_reading_the_original_again(): void
    {
        $this->store($this->jpeg(800, 600));
        $first = $this->thumbnail();
        $saved = Storage::disk('record_thumbnails')->get($first);

        $this->store('ya no es una imagen');

        $this->assertSame($first, $this->thumbnail());
        $this->assertSame($saved, Storage::disk('record_thumbnails')->get($first));
    }

    public function test_returns_null_and_saves_nothing_when_the_file_is_not_an_image(): void
    {
        $this->store('esto no es una imagen');

        $this->assertNull($this->thumbnail());
        $this->assertSame([], Storage::disk('record_thumbnails')->allFiles());
    }

    public function test_returns_null_when_the_original_is_missing(): void
    {
        $this->assertNull($this->thumbnail());
    }

    public function test_skips_photos_that_would_not_fit_in_memory(): void
    {
        $this->store($this->jpeg(2000, 2000));
        $limit = ini_get('memory_limit');

        try {
            ini_set('memory_limit', (string) (memory_get_usage(true) + 8 * 1024 * 1024));

            $this->assertNull($this->thumbnail());
        } finally {
            ini_set('memory_limit', $limit);
        }

        $this->assertSame([], Storage::disk('record_thumbnails')->allFiles());
    }

    public function test_applies_the_exif_orientation_the_way_browsers_do(): void
    {
        if (! function_exists('exif_read_data')) {
            $this->markTestSkipped('La extensión exif no está disponible.');
        }

        $this->store($this->jpegWithOrientation(800, 400, 6));

        // Una foto horizontal marcada como «girar 90°» se muestra vertical.
        $this->assertSame([320, 640, 'image/jpeg'], $this->dimensionsOf($this->thumbnail()));
    }

    public function test_rotates_a_small_photo_marked_for_rotation_instead_of_keeping_it(): void
    {
        if (! function_exists('exif_read_data')) {
            $this->markTestSkipped('La extensión exif no está disponible.');
        }

        $this->store($this->jpegWithOrientation(200, 100, 6));

        $this->assertSame([100, 200, 'image/jpeg'], $this->dimensionsOf($this->thumbnail()));
    }

    public function test_leaves_photos_without_a_rotation_mark_as_they_are(): void
    {
        $this->store($this->jpegWithOrientation(800, 400, 1));

        $this->assertSame([320, 160, 'image/jpeg'], $this->dimensionsOf($this->thumbnail()));
    }
}
