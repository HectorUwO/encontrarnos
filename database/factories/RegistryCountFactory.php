<?php

namespace Database\Factories;

use App\Enums\AgeRange;
use App\Enums\DisappearanceStatus;
use App\Enums\MexicanState;
use App\Enums\Sex;
use App\Models\RegistryCount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistryCount>
 */
class RegistryCountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'state' => fake()->randomElement(MexicanState::cases()),
            'municipality' => mb_strtoupper(fake('es_ES')->city()),
            'month' => fake()->dateTimeBetween('-5 years')->format('Y-m-01'),
            'sex' => fake()->randomElement([Sex::Female, Sex::Male]),
            'age_range' => fake()->randomElement(AgeRange::cases()),
            'status' => DisappearanceStatus::Disappeared,
            'confidential' => false,
            'total' => fake()->numberBetween(1, 20),
        ];
    }

    /**
     * Registros que el registro nacional mantiene confidenciales: solo se
     * conoce el estado y el municipio.
     */
    public function confidential(): static
    {
        return $this->state([
            'month' => null,
            'sex' => null,
            'age_range' => null,
            'status' => null,
            'confidential' => true,
        ]);
    }

    public function undated(): static
    {
        return $this->state(['month' => null]);
    }

    public function inState(?MexicanState $state): static
    {
        return $this->state(['state' => $state]);
    }

    public function inMonth(string $month): static
    {
        return $this->state(['month' => $month.'-01']);
    }
}
