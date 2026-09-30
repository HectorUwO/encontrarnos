<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\InformationReport;
use App\Models\PersonRecord;
use App\Models\PersonRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_redirects_guests_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_summarizes_only_the_users_own_requests(): void
    {
        $user = User::factory()->create();
        PersonRecord::factory()->count(3)->create();
        PersonRequest::factory()->for($user)->create();
        PersonRequest::factory()->for($user)->approved()->create();
        PersonRequest::factory()->for($user)->approved()->create(['closed_at' => now(), 'closed_reason' => 'resolved']);
        PersonRequest::factory()->for(User::factory())->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('summary.total', 3)
                ->where('summary.pending', 1)
                ->where('summary.published', 1)
                ->where('summary.closed', 1)
                ->where('summary.records', 3)
                ->has('myRequests', 3));
    }

    public function test_includes_requests_sent_as_a_guest_with_the_users_verified_email(): void
    {
        $user = User::factory()->create(['email' => 'familia@example.com']);
        PersonRequest::factory()->create(['user_id' => null, 'contact_email' => 'familia@example.com']);
        PersonRequest::factory()->create(['user_id' => null, 'contact_email' => 'otra@example.com']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('summary.total', 1));
    }

    public function test_lists_the_information_received_with_its_sender_and_state(): void
    {
        $user = User::factory()->create();
        $request = PersonRequest::factory()->for($user)->approved()->create(['name' => 'Ana Torres']);
        $sender = User::factory()->create(['name' => 'Luis Vega', 'email' => 'luis@example.com']);
        InformationReport::create(['user_id' => $sender->id, 'person_request_id' => $request->id, 'message' => 'La vi ayer.', 'phone' => '3111234567']);
        InformationReport::create(['user_id' => $sender->id, 'person_request_id' => $request->id, 'message' => 'Ya la atendí.', 'attended_at' => now()]);
        // Información de la solicitud de otra persona: no se muestra.
        InformationReport::create(['user_id' => $sender->id, 'person_request_id' => PersonRequest::factory()->create()->id, 'message' => 'Ajena.']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.unattended', 1)
                ->has('reports', 2)
                ->where('myRequests.0.reports_count', 2)
                ->where('myRequests.0.unattended_count', 1)
                ->where('reports.0.sender_email', 'luis@example.com'));
    }
}
