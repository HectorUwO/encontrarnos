<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Todo lo que trae el registro nacional sobre la persona, incluidos los
     * datos personales (nacimiento y domicilio). Quién puede verlos lo decide
     * la aplicación (ver la habilidad `view-sensitive-record-data`), no la base.
     */
    public function up(): void
    {
        Schema::table('person_records', function (Blueprint $table): void {
            // Fechas y trámite del registro.
            $table->date('noticed_date')->nullable()->after('event_date');
            $table->date('registered_date')->nullable()->after('noticed_date');
            $table->timestamp('source_updated_at')->nullable()->after('registered_date');
            $table->string('origin', 120)->nullable()->after('authority');
            $table->boolean('search_only')->nullable()->after('origin');
            $table->json('referred_to')->nullable()->after('search_only');
            $table->string('migration_file', 255)->nullable()->after('referred_to');

            // Edad al momento de capturar la ficha.
            $table->unsignedSmallInteger('registered_age_years')->nullable()->after('current_age');
            $table->unsignedSmallInteger('registered_age_months')->nullable()->after('registered_age_years');
            $table->unsignedSmallInteger('registered_age_days')->nullable()->after('registered_age_months');

            // Datos de la persona.
            $table->string('nationality', 80)->nullable()->after('sex');
            $table->boolean('speaks_spanish')->nullable()->after('nationality');
            $table->boolean('has_disability')->nullable()->after('speaks_spanish');
            $table->string('disability_type', 255)->nullable()->after('has_disability');

            // Datos personales sensibles.
            $table->date('birth_date')->nullable();
            $table->string('birth_state', 80)->nullable();
            $table->string('birth_place', 255)->nullable();
            $table->string('street', 255)->nullable();
            $table->string('exterior_number', 40)->nullable();
            $table->string('interior_number', 40)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('neighborhood', 255)->nullable();

            // Identificador interno de la dependencia de origen (nunca se muestra).
            $table->unsignedInteger('source_authority_id')->nullable()->after('source_agency_id');
        });
    }

    public function down(): void
    {
        Schema::table('person_records', function (Blueprint $table): void {
            $table->dropColumn([
                'noticed_date', 'registered_date', 'source_updated_at', 'origin', 'search_only', 'referred_to',
                'migration_file', 'registered_age_years', 'registered_age_months', 'registered_age_days',
                'nationality', 'speaks_spanish', 'has_disability', 'disability_type', 'birth_date', 'birth_state',
                'birth_place', 'street', 'exterior_number', 'interior_number', 'postal_code', 'neighborhood',
                'source_authority_id',
            ]);
        });
    }
};
