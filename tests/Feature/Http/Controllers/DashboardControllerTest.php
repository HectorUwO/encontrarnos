<?php

namespace Tests\Feature\Http\Controllers;

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

    public function test_summarizes_the_records_and_only_the_users_own_requests(): void
    {
        $user = User::factory()->create();
        PersonRecord::factory()->count(8)->create();
        PersonRecord::factory()->unpublished()->create();
        PersonRequest::factory()->for($user)->count(2)->create();
        PersonRequest::factory()->for(User::factory())->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('totals', ['records' => 8, 'my_requests' => 2])
                ->has('latestRecords.data', 6)
                ->has('myRequests.data', 2));
    }
}
