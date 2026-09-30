<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\MexicanState;
use App\Models\RegistryCount;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StatisticsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_renders_the_statistics_of_the_whole_country(): void
    {
        RegistryCount::factory()->inState(MexicanState::Jalisco)->create(['month' => '2024-03-01', 'total' => 10]);
        RegistryCount::factory()->inState(MexicanState::Sinaloa)->confidential()->create(['total' => 5]);

        $this->get(route('statistics'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Estadisticas')
                ->where('statistics.has_data', true)
                ->where('statistics.filters', ['state' => null, 'from' => null, 'to' => null])
                ->where('statistics.totals.registry', 15)
                ->where('statistics.summary.scope', 'México')
                ->has('statistics.entities', 32)
                ->has('statistics.timeline', 1)
                ->has('statistics.profile.sex', 3)
                ->has('statistics.municipalities')
                ->has('states', 32));
    }

    public function test_limits_the_statistics_of_the_country_to_a_period(): void
    {
        RegistryCount::factory()->inState(MexicanState::Jalisco)->create(['month' => '2024-03-01', 'total' => 10]);
        RegistryCount::factory()->inState(MexicanState::Jalisco)->create(['month' => '2025-03-01', 'total' => 4]);
        RegistryCount::factory()->inState(MexicanState::Sinaloa)->create(['month' => '2024-03-01', 'total' => 7]);

        $this->get(route('statistics', ['from' => 2024, 'to' => 2024]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('statistics.filters', ['state' => null, 'from' => 2024, 'to' => 2024])
                ->where('statistics.summary.scope', 'México')
                ->where('statistics.summary.total', 17));
    }

    public function test_sends_the_old_links_with_a_state_to_the_page_of_that_state(): void
    {
        $this->get(route('statistics', ['state' => 'estado-de-mexico']))
            ->assertStatus(301)
            ->assertRedirect(route('statistics.state', ['state' => 'estado-de-mexico']));

        $this->get(route('statistics', ['state' => 'jalisco', 'from' => 2020, 'to' => 2024]))
            ->assertRedirect(route('statistics.state', ['state' => 'jalisco', 'from' => 2020, 'to' => 2024]));
    }

    public function test_shows_an_empty_state_when_no_counts_were_imported(): void
    {
        $this->get(route('statistics'))
            ->assertInertia(fn (Assert $page) => $page->where('statistics', ['has_data' => false]));
    }

    public function test_rejects_an_unknown_state(): void
    {
        $this->from(route('statistics'))
            ->get(route('statistics', ['state' => 'atlantis']))
            ->assertRedirect(route('statistics'))
            ->assertInvalid(['state' => 'El estado no es válido.']);
    }

    public function test_rejects_a_period_that_ends_before_it_starts(): void
    {
        $this->from(route('statistics'))
            ->get(route('statistics', ['from' => 2025, 'to' => 2020]))
            ->assertRedirect(route('statistics'))
            ->assertInvalid(['to' => 'El año final no puede ser anterior al inicial.']);
    }

    public function test_rejects_years_that_are_not_numbers_or_out_of_range(): void
    {
        $this->from(route('statistics'))
            ->get(route('statistics', ['from' => 'abc', 'to' => 3000]))
            ->assertInvalid([
                'from' => 'El año inicial debe ser un número.',
                'to' => 'El año final debe estar entre 1900 y 2100.',
            ]);
    }

    public function test_limits_how_many_times_a_client_can_ask_for_statistics(): void
    {
        RateLimiter::for('statistics', fn () => Limit::perMinute(2));

        $this->get(route('statistics'))->assertOk();
        $this->get(route('statistics'))->assertOk();
        $this->get(route('statistics'))->assertTooManyRequests();
    }
}
