<?php

namespace Database\Seeders;

use App\Models\PersonRecord;
use Illuminate\Database\Seeder;

class PersonRecordSeeder extends Seeder
{
    /**
     * Datos ficticios para desarrollo cuando todavía no se han importado las
     * fichas reales (`php artisan records:import-rnpdno`).
     */
    public function run(): void
    {
        if (PersonRecord::query()->exists()) {
            return;
        }

        PersonRecord::factory()->count(40)->create();
    }
}
