<?php

namespace Tests\Feature;

use App\Models\PersonRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_puts_pending_work_first_oldest_request_first(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->unverified()->create();
        $old = PersonRequest::factory()->create(['created_at' => now()->subDays(3)]);
        PersonRequest::factory()->create(['created_at' => now()->subDay()]);
        PersonRequest::factory()->approved()->create();

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Index')
                ->where('pending.requests', 2)
                ->where('pending.unverified', 1)
                ->has('pendingRequests', 2)
                ->where('pendingRequests.0.id', $old->id)
                ->where('totals.published', 1)
                ->where('totals.requests', 3));
    }

    public function test_pending_badge_is_shared_with_admins_only(): void
    {
        PersonRequest::factory()->count(2)->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.requests'))
            ->assertInertia(fn (Assert $page) => $page->where('adminPending', 2));

        $this->actingAs($member)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('adminPending', null));
    }
}
