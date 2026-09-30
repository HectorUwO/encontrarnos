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
            'sex' => 'female',
            'age' => 29,
            'state' => 'nayarit',
            'municipality' => 'Tepic',
            'event_date' => '2026-03-02',
            'description' => 'Cabello negro, 1.60 m, cicatriz en la ceja izquierda.',
            'traits' => ['cabello' => 'Negro', 'ojos' => 'Cafés', 'desconocido' => 'ignorado'],
            'clothing' => 'Blusa azul y jeans',
            'distinguishing_marks' => 'Cicatriz en la ceja izquierda',
            'institution' => 'Fiscalía de Nayarit',
            'contact_email' => 'familia@example.com',
            'contact_phone' => '311 123 4567',
            ...$overrides,
        ];
    }

    public function test_catalog_lists_only_approved_requests_without_contact_data(): void
    {
        PersonRequest::factory()->approved()->create(['municipality' => 'Tepic', 'contact_email' => 'privado@example.com', 'contact_phone' => '3111234567']);
        PersonRequest::factory()->create(['municipality' => 'Pendiente']);
        PersonRequest::factory()->rejected()->create(['municipality' => 'Rechazada']);

        $response = $this->get(route('requests'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Public/Solicitudes')
            ->has('requests.data', 1)
            ->where('requests.data.0.place', 'Tepic, Nayarit')
            ->where('totalPublished', 1)
            ->has('options.states', 32)
            ->has('options.types', 2)
            ->missing('requests.data.0.contact_email')
            ->missing('requests.data.0.contact_phone'));
        $this->assertStringNotContainsString('privado@example.com', $response->getContent());
        $this->assertStringNotContainsString('3111234567', $response->getContent());
    }

    public function test_catalog_filters_by_state_age_type_and_text(): void
    {
        PersonRequest::factory()->approved()->create(['name' => 'Ana Torres', 'state' => 'nayarit', 'municipality' => 'Tepic', 'age' => 25, 'type' => 'search']);
        PersonRequest::factory()->approved()->create(['name' => 'Luis Pérez', 'state' => 'jalisco', 'municipality' => 'Zapopan', 'place' => 'Zapopan, Jalisco', 'age' => 45, 'type' => 'identification']);

        $names = fn (array $query): array => collect($this->get(route('requests', $query))->viewData('page')['props']['requests']['data'])->pluck('name')->all();

        $this->assertSame(['Ana Torres'], $names(['state' => 'nayarit']));
        $this->assertSame(['Luis Pérez'], $names(['age' => '40-plus']));
        $this->assertSame(['Luis Pérez'], $names(['type' => 'identification']));
        $this->assertSame(['Ana Torres'], $names(['q' => 'tepic']));
        $this->assertSame(['Luis Pérez'], $names(['q' => 'jalisco']));
        $this->assertSame([], $names(['q' => 'nadie']));
    }

    public function test_catalog_rejects_unknown_filters(): void
    {
        $this->from(route('requests'))->get(route('requests', ['state' => 'atlantis']))->assertRedirect(route('requests'));
    }

    public function test_catalog_links_the_photo_only_when_the_request_has_one(): void
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

    public function test_the_form_renders_with_its_options(): void
    {
        $this->get(route('requests.create', ['tipo' => 'identification']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/SolicitudCrear')
                ->where('initialType', 'identification')
                ->has('options.states', 32)
                ->has('options.sexes', 3)
                ->has('options.traits', 10));

        $this->get(route('requests.create', ['tipo' => 'nada']))
            ->assertInertia(fn (Assert $page) => $page->where('initialType', 'search'));
    }

    public function test_stores_a_pending_request_from_a_guest(): void
    {
        $this->post(route('requests.store'), $this->validPayload())->assertRedirect(route('requests'));

        $this->assertDatabaseHas('person_requests', [
            'type' => 'search',
            'status' => 'pending',
            'name' => 'Ana Torres',
            'sex' => 'female',
            'age' => 29,
            'state' => 'nayarit',
            'municipality' => 'Tepic',
            'place' => 'Tepic, Nayarit',
            'clothing' => 'Blusa azul y jeans',
            'institution' => 'Fiscalía de Nayarit',
            'contact_email' => 'familia@example.com',
            'contact_phone' => '311 123 4567',
            'user_id' => null,
            'photo_path' => null,
        ]);
        // Solo se guardan los rasgos conocidos.
        $this->assertSame(['cabello' => 'Negro', 'ojos' => 'Cafés'], PersonRequest::firstOrFail()->traits);
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

    public function test_a_search_needs_a_name_but_an_identification_does_not(): void
    {
        $this->post(route('requests.store'), $this->validPayload(['name' => null]))
            ->assertInvalid(['name' => 'Escribe el nombre de la persona que buscas.']);

        $this->post(route('requests.store'), $this->validPayload(['type' => 'identification', 'name' => null]))
            ->assertRedirect(route('requests'));
    }

    public function test_requires_the_mandatory_fields(): void
    {
        $this->post(route('requests.store'), [])->assertInvalid([
            'type' => 'Elige el tipo de solicitud.',
            'state' => 'Elige el estado.',
            'municipality' => 'Indica el municipio.',
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
            'unknown state' => [['state' => 'atlantis'], 'state', 'El estado no es válido.'],
            'malformed email' => [['contact_email' => 'no-es-un-correo'], 'contact_email', 'Escribe un correo electrónico válido.'],
            'malformed phone' => [['contact_phone' => 'abc'], 'contact_phone', 'Escribe un teléfono válido.'],
            'future date' => [['event_date' => '2999-01-01'], 'event_date', 'La fecha no puede ser futura.'],
            'negative age' => [['age' => -1], 'age', 'La edad no puede ser negativa.'],
            'description too long' => [['description' => str_repeat('a', 3001)], 'description', 'La descripción no puede tener más de 3000 caracteres.'],
            'municipality too long' => [['municipality' => str_repeat('a', 121)], 'municipality', 'El municipio no puede tener más de 120 caracteres.'],
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

    public function test_the_ficha_shows_an_approved_request_without_contact_data(): void
    {
        $request = PersonRequest::factory()->approved()->create([
            'name' => 'Ana Torres',
            'traits' => ['cabello' => 'Negro'],
            'contact_email' => 'privado@example.com',
            'contact_phone' => '3111234567',
        ]);

        $response = $this->get(route('requests.show', $request));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Public/SolicitudFicha')
            ->where('request.data.name', 'Ana Torres')
            ->where('request.data.traits.0.label', 'Cabello')
            ->where('canOffer', true)
            ->missing('request.data.contact_email'));
        $this->assertStringNotContainsString('privado@example.com', $response->getContent());
        $this->assertStringNotContainsString('3111234567', $response->getContent());
    }

    public function test_a_pending_ficha_is_only_visible_to_its_author_and_admins(): void
    {
        $author = User::factory()->create();
        $request = PersonRequest::factory()->create(['user_id' => $author->id]);

        $this->get(route('requests.show', $request))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('requests.show', $request))->assertNotFound();

        $this->actingAs($author)->get(route('requests.show', $request))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canOffer', false));
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('requests.show', $request))->assertOk();
    }
}
