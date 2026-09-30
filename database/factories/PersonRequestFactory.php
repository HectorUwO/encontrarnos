<?php

namespace Database\Factories;

use App\Enums\PersonRequestStatus;
use App\Enums\PersonRequestType;
use App\Models\PersonRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonRequest>
 */
class PersonRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(PersonRequestType::cases()),
            'status' => PersonRequestStatus::Pending,
            'name' => fake('es_ES')->name(),
            'place' => fake('es_ES')->city().', '.fake('es_ES')->state(),
            'description' => fake('es_ES')->paragraph(),
            'contact_email' => fake()->unique()->safeEmail(),
        ];
    }

    public function approved(): static
    {
        return $this->state(['status' => PersonRequestStatus::Approved]);
    }

    public function rejected(): static
    {
        return $this->state(['status' => PersonRequestStatus::Rejected]);
    }

    public function withPhoto(): static
    {
        return $this->state(fn (): array => [
            'photo_path' => 'person-requests/'.fake()->unique()->uuid().'.jpg',
        ]);
    }
}
