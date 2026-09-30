<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\PersonRequest;
use App\Models\User;
use App\Services\Photos\PhotoCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PersonRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'type' => 'search',
            'name' => 'Ana Torres',
            'place' => 'Tepic, Nayarit',
            'description' => 'Cabello negro, 1.60 m, cicatriz en la ceja izquierda.',
            'contact_email' => 'familia@example.com',
            ...$overrides,
        ];
    }

    public function test_board_lists_only_approved_requests_without_contact_data(): void
    {
        PersonRequest::factory()->approved()->create(['place' => 'Tepic, Nayarit', 'contact_email' => 'privado@example.com']);
        PersonRequest::factory()->create(['place' => 'Pendiente']);
        PersonRequest::factory()->rejected()->create(['place' => 'Rechazada']);

        $response = $this->get(route('requests'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Public/Solicitudes')
            ->has('requests.data', 1)
            ->where('requests.data.0.place', 'Tepic, Nayarit')
            ->missing('requests.data.0.contact_email'));
        $this->assertStringNotContainsString('privado@example.com', $response->getContent());
    }

    public function test_board_links_the_photo_only_when_the_request_has_one(): void
    {
        $withPhoto = PersonRequest::factory()->approved()->withPhoto()->create(['created_at' => '2026-02-02']);
        PersonRequest::factory()->approved()->create(['created_at' => '2026-02-01']);
        $version = PhotoCache::version($withPhoto->photo_path);

        $this->get(route('requests'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('requests.data.0.photo', "/solicitudes/{$withPhoto->id}/foto?size=medium&v={$version}")
                ->where('requests.data.0.photo_thumb', "/solicitudes/{$withPhoto->id}/foto?size=thumb&v={$version}")
                ->where('requests.data.1.photo', null)
                ->where('requests.data.1.photo_thumb', null));
    }

    public function test_stores_a_pending_request_from_a_guest(): void
    {
        $this->post(route('requests.store'), $this->validPayload())->assertRedirect();

        $this->assertDatabaseHas('person_requests', [
            'type' => 'search',
            'status' => 'pending',
            'name' => 'Ana Torres',
            'place' => 'Tepic, Nayarit',
            'description' => 'Cabello negro, 1.60 m, cicatriz en la ceja izquierda.',
            'contact_email' => 'familia@example.com',
            'user_id' => null,
            'photo_path' => null,
        ]);
    }

    public function test_stores_the_uploaded_photo_on_the_private_disk(): void
    {
        Storage::fake('local');

        $this->post(route('requests.store'), $this->validPayload([
            'photo' => UploadedFile::fake()->image('foto.jpg', 600, 400),
        ]))->assertRedirect();

        $request = PersonRequest::query()->firstOrFail();

        $this->assertTrue($request->hasPhoto());
        Storage::disk('local')->assertExists($request->photo_path);
    }

    public function test_associates_the_request_with_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('requests.store'), $this->validPayload())->assertRedirect();

        $this->assertDatabaseHas('person_requests', ['user_id' => $user->id]);
    }

    public function test_ignores_the_status_and_user_sent_by_the_client(): void
    {
        $this->post(route('requests.store'), $this->validPayload(['status' => 'approved', 'user_id' => 999]))
            ->assertRedirect();

        $this->assertDatabaseHas('person_requests', ['status' => 'pending', 'user_id' => null]);
    }

    public function test_requires_the_mandatory_fields(): void
    {
        $this->post(route('requests.store'), [])->assertInvalid([
            'type' => 'Elige el tipo de solicitud.',
            'place' => 'Indica el lugar (municipio y estado).',
            'description' => 'Agrega una descripción.',
            'contact_email' => 'Indica un correo de contacto.',
        ]);

        $this->assertDatabaseCount('person_requests', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidValues')]
    public function test_rejects_invalid_values(array $overrides, string $field, string $message): void
    {
        $this->post(route('requests.store'), $this->validPayload($overrides))->assertInvalid([$field => $message]);

        $this->assertDatabaseCount('person_requests', 0);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidValues(): array
    {
        return [
            'unknown type' => [['type' => 'delete'], 'type', 'El tipo de solicitud no es válido.'],
            'malformed email' => [['contact_email' => 'no-es-un-correo'], 'contact_email', 'Escribe un correo electrónico válido.'],
            'description too long' => [['description' => str_repeat('a', 3001)], 'description', 'La descripción no puede tener más de 3000 caracteres.'],
            'place too long' => [['place' => str_repeat('a', 151)], 'place', 'El lugar no puede tener más de 150 caracteres.'],
            'name too long' => [['name' => str_repeat('a', 121)], 'name', 'El nombre no puede tener más de 120 caracteres.'],
        ];
    }

    public function test_rejects_a_photo_that_is_not_an_image(): void
    {
        Storage::fake('local');

        $this->post(route('requests.store'), $this->validPayload([
            'photo' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
        ]))->assertInvalid(['photo' => 'Elige una imagen JPG, PNG o WEBP.']);

        $this->assertDatabaseCount('person_requests', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_rejects_a_photo_heavier_than_ten_megabytes(): void
    {
        Storage::fake('local');

        $this->post(route('requests.store'), $this->validPayload([
            'photo' => UploadedFile::fake()->image('foto.jpg')->size(10241),
        ]))->assertInvalid(['photo' => 'La fotografía debe pesar menos de 10 MB.']);

        $this->assertDatabaseCount('person_requests', 0);
    }

    public function test_limits_submissions_to_five_per_hour(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('requests.store'), $this->validPayload(['contact_email' => "familia{$attempt}@example.com"]))
                ->assertRedirect();
        }

        $this->post(route('requests.store'), $this->validPayload())->assertTooManyRequests();

        $this->assertDatabaseCount('person_requests', 5);
    }
}
