<?php

namespace Database\Factories;

use App\Enums\DisappearanceStatus;
use App\Enums\MexicanState;
use App\Enums\RecordType;
use App\Enums\Sex;
use App\Models\PersonRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonRecord>
 */
class PersonRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sex = fake()->randomElement([Sex::Female, Sex::Male]);
        $age = fake()->numberBetween(1, 85);

        return [
            'folio' => sprintf('EN-%06d', fake()->unique()->numberBetween(1, 999999)),
            'type' => RecordType::MissingPerson,
            'disappearance_status' => DisappearanceStatus::Disappeared,
            'sex' => $sex,
            'name' => mb_strtoupper(fake('es_ES')->name($sex === Sex::Female ? 'female' : 'male')),
            'age' => $age,
            'current_age' => $age,
            'state' => fake()->randomElement(MexicanState::cases()),
            'municipality' => mb_strtoupper(fake('es_ES')->city()),
            'event_date' => fake()->dateTimeBetween('-2 years'),
            'description' => 'Complexión: media. Cabello: negro corto.',
            'traits' => ['complexion' => 'MEDIA', 'cabello' => 'NEGRO CORTO'],
            'authority' => 'COMISION LOCAL DE BUSQUEDA DE PERSONAS',
            'published_at' => now(),
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['published_at' => null]);
    }

    public function withPhoto(): static
    {
        return $this->state(function (): array {
            $sha256 = hash('sha256', fake()->unique()->uuid());

            return [
                'photo_sha256' => $sha256,
                'photo_path' => 'imagenes/'.substr($sha256, 0, 2).'/'.$sha256.'.jpg',
            ];
        });
    }

    public function inState(MexicanState $state): static
    {
        return $this->state(['state' => $state]);
    }

    public function ofAge(?int $age): static
    {
        return $this->state(['age' => $age, 'current_age' => $age]);
    }
}
