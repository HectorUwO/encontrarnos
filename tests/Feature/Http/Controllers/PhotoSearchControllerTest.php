<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\PersonRecord;
use App\Services\PhotoSearch\PhotoMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PhotoSearchControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_renders_the_photo_search_page(): void
    {
        $this->get(route('photo-search'))
            ->assertInertia(fn (Assert $page) => $page->component('Public/BusquedaFotografia'));
    }

    public function test_reports_that_the_matching_engine_is_not_available_by_default(): void
    {
        $this->post(route('photo-search.store'), ['photo' => UploadedFile::fake()->image('foto.jpg')])
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/BusquedaFotografia')
                ->where('search.available', false)
                ->has('search.matches', 0));
    }

    public function test_does_not_keep_the_uploaded_photo(): void
    {
        Storage::fake('local');

        $this->post(route('photo-search.store'), ['photo' => UploadedFile::fake()->image('foto.jpg')]);

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_returns_the_records_found_by_the_matching_engine(): void
    {
        $record = PersonRecord::factory()->create(['folio' => 'EN-000007']);

        $this->app->instance(PhotoMatcher::class, new class($record) implements PhotoMatcher
        {
            public function __construct(private readonly PersonRecord $record) {}

            public function isAvailable(): bool
            {
                return true;
            }

            public function match(UploadedFile $photo): Collection
            {
                return collect([$this->record]);
            }
        });

        $this->post(route('photo-search.store'), ['photo' => UploadedFile::fake()->image('foto.jpg')])
            ->assertInertia(fn (Assert $page) => $page
                ->where('search.available', true)
                ->has('search.matches', 1)
                ->where('search.matches.0.folio', 'EN-000007'));
    }

    public function test_requires_a_photo(): void
    {
        $this->post(route('photo-search.store'), [])
            ->assertInvalid(['photo' => 'Selecciona una fotografía.']);
    }

    public function test_rejects_a_file_that_is_not_an_image(): void
    {
        $this->post(route('photo-search.store'), [
            'photo' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
        ])->assertInvalid(['photo' => 'Selecciona una imagen JPG, PNG o WEBP.']);
    }

    public function test_limits_searches_to_ten_per_minute(): void
    {
        foreach (range(1, 10) as $attempt) {
            $this->post(route('photo-search.store'), ['photo' => UploadedFile::fake()->image('foto.jpg')])->assertOk();
        }

        $this->post(route('photo-search.store'), ['photo' => UploadedFile::fake()->image('foto.jpg')])
            ->assertTooManyRequests();
    }
}
