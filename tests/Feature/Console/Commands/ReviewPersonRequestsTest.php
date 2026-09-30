<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\PersonRequestStatus;
use App\Enums\PersonRequestType;
use App\Models\PersonRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewPersonRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_the_pending_requests_oldest_first(): void
    {
        PersonRequest::factory()->create([
            'id' => 7, 'type' => PersonRequestType::Search, 'name' => 'ANA RUIZ', 'place' => 'TEPIC, NAYARIT',
            'contact_email' => 'ana@example.test', 'description' => 'Vista por última vez en el centro.',
            'created_at' => now()->subDay(),
        ]);
        PersonRequest::factory()->withPhoto()->create([
            'id' => 3, 'type' => PersonRequestType::Identification, 'name' => 'LUIS PEREZ', 'place' => 'COLIMA, COLIMA',
            'contact_email' => 'luis@example.test', 'description' => 'Tiene un tatuaje en el brazo.',
            'created_at' => now()->subDays(2),
        ]);
        PersonRequest::factory()->approved()->create(['name' => 'YA APROBADA']);
        PersonRequest::factory()->rejected()->create(['name' => 'YA RECHAZADA']);

        $this->artisan('requests:review')
            ->expectsTable(
                ['Referencia', 'Tipo', 'Nombre', 'Lugar', 'Contacto', 'Foto', 'Descripción'],
                [
                    ['SOL-000003', 'Identificación de una persona', 'LUIS PEREZ', 'COLIMA, COLIMA', 'luis@example.test', 'sí', 'Tiene un tatuaje en el brazo.'],
                    ['SOL-000007', 'Búsqueda de una persona', 'ANA RUIZ', 'TEPIC, NAYARIT', 'ana@example.test', 'no', 'Vista por última vez en el centro.'],
                ],
            )
            ->assertSuccessful();
    }

    public function test_says_so_when_nothing_is_waiting_for_review(): void
    {
        PersonRequest::factory()->approved()->create();

        $this->artisan('requests:review')
            ->expectsOutputToContain('No hay solicitudes pendientes.')
            ->assertSuccessful();
    }

    public function test_approves_a_request_by_its_reference(): void
    {
        $request = PersonRequest::factory()->create(['id' => 12]);

        $this->artisan('requests:review SOL-000012 approve')
            ->expectsOutputToContain('SOL-000012: Pendiente de revisión → Aprobada.')
            ->assertSuccessful();

        $this->assertSame(PersonRequestStatus::Approved, $request->fresh()->status);
        $this->assertCount(1, PersonRequest::query()->approved()->get());
    }

    public function test_rejects_a_request_by_its_number_and_can_take_down_an_approved_one(): void
    {
        $pending = PersonRequest::factory()->create(['id' => 5]);
        $approved = PersonRequest::factory()->approved()->create(['id' => 6]);

        $this->artisan('requests:review 5 reject')->assertSuccessful();
        $this->artisan('requests:review 6 reject')
            ->expectsOutputToContain('SOL-000006: Aprobada → Rechazada.')
            ->assertSuccessful();

        $this->assertSame(PersonRequestStatus::Rejected, $pending->fresh()->status);
        $this->assertSame(PersonRequestStatus::Rejected, $approved->fresh()->status);
    }

    public function test_fails_when_the_request_does_not_exist(): void
    {
        $this->artisan('requests:review SOL-000099 approve')
            ->expectsOutputToContain('No existe la solicitud SOL-000099.')
            ->assertFailed();
    }

    public function test_fails_without_touching_anything_when_the_decision_is_missing_or_unknown(): void
    {
        $request = PersonRequest::factory()->create();

        $this->artisan("requests:review {$request->id}")
            ->expectsOutputToContain('Indica la decisión: approve o reject.')
            ->assertFailed();
        $this->artisan("requests:review {$request->id} publicar")->assertFailed();

        $this->assertSame(PersonRequestStatus::Pending, $request->fresh()->status);
    }
}
