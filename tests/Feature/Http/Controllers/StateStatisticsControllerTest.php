<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\MexicanState;
use App\Models\RegistryCount;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StateStatisticsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_renders_the_page_of_one_state_with_its_figures_and_all_its_municipalities(): void
    {
        RegistryCount::factory()->inState(MexicanState::Jalisco)->create(['municipality' => 'ZAPOPAN', 'month' => '2024-03-01', 'total' => 10]);
        RegistryCount::factory()->inState(MexicanState::Jalisco)->confidential()->create(['municipality' => 'GUADALAJARA', 'total' => 30]);
        RegistryCount::factory()->inState(MexicanState::Sinaloa)->create(['municipality' => 'CULIACÁN', 'month' => '2024-03-01', 'total' => 5]);

        $this->get(route('statistics.state', ['state' => 'jalisco']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/EstadisticasEstado')
                ->where('statistics.has_data', true)
                ->where('statistics.filters', ['state' => 'jalisco', 'from' => null, 'to' => null])
                ->where('statistics.summary.scope', 'Jalisco')
                ->where('statistics.summary.total', 40)
                ->where('statistics.summary.rank', 1)
                ->where('statistics.summary.national.total', 45)
                ->has('statistics.entities', 32)
                ->has('statistics.timeline', 1)
                ->has('statistics.municipalities', 2)
                ->where('statistics.municipalities.0.name', 'Guadalajara')
                ->where('statistics.unknown_municipality', 0)
                ->has('states', 32));
    }

    public function test_narrows_the_state_to_a_period(): void
    {
        RegistryCount::factory()->inState(MexicanState::Jalisco)->create(['month' => '2024-03-01', 'total' => 10]);
        RegistryCount::factory()->inState(MexicanState::Jalisco)->create(['month' => '2025-03-01', 'total' => 4]);
        RegistryCount::factory()->inState(MexicanState::Jalisco)->confidential()->create(['total' => 30]);

        $this->get(route('statistics.state', ['state' => 'jalisco', 'from' => 2024, 'to' => 2024]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('statistics.filters', ['state' => 'jalisco', 'from' => 2024, 'to' => 2024])
                ->where('statistics.summary.total', 10));
    }

    /**
     * @return array<string, array{MexicanState}>
     */
    public static function states(): array
    {
        return collect(MexicanState::cases())
            ->mapWithKeys(fn (MexicanState $state): array => [$state->value => [$state]])
            ->all();
    }

    #[DataProvider('states')]
    public function test_every_state_has_its_own_page(MexicanState $state): void
    {
        RegistryCount::factory()->inState($state)->create(['total' => 3]);

        $this->get(route('statistics.state', $state))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/EstadisticasEstado')
                ->where('statistics.filters.state', $state->value)
                ->where('statistics.summary.scope', $state->label())
                ->where('statistics.summary.total', 3));
    }

    public function test_returns_404_for_a_state_that_does_not_exist(): void
    {
        $this->get('/estadisticas/atlantis')->assertNotFound();
    }

    public function test_shows_an_empty_state_when_no_counts_were_imported(): void
    {
        $this->get(route('statistics.state', ['state' => 'jalisco']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/EstadisticasEstado')
                ->where('statistics', ['has_data' => false])
                ->has('states', 32));
    }

    public function test_an_invalid_period_sends_the_visitor_back_to_the_page_of_the_state_without_filters(): void
    {
        $this->get(route('statistics.state', ['state' => 'estado-de-mexico', 'from' => 2025, 'to' => 2020]))
            ->assertRedirect(route('statistics.state', ['state' => 'estado-de-mexico']))
            ->assertInvalid(['to' => 'El año final no puede ser anterior al inicial.']);

        $this->get(route('statistics.state', ['state' => 'jalisco', 'from' => 'abc', 'to' => 3000]))
            ->assertInvalid([
                'from' => 'El año inicial debe ser un número.',
                'to' => 'El año final debe estar entre 1900 y 2100.',
            ]);
    }

    public function test_limits_how_many_times_a_client_can_ask_for_a_state(): void
    {
        RateLimiter::for('statistics', fn () => Limit::perMinute(2));

        $this->get(route('statistics.state', ['state' => 'jalisco']))->assertOk();
        $this->get(route('statistics.state', ['state' => 'sinaloa']))->assertOk();
        $this->get(route('statistics.state', ['state' => 'colima']))->assertTooManyRequests();
    }
}
