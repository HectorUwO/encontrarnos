<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las solicitudes tienen su propia ficha, con la misma estructura que las
     * fichas de personas desaparecidas pero en una tabla aparte.
     */
    public function up(): void
    {
        Schema::table('person_requests', function (Blueprint $table): void {
            $table->string('sex', 16)->nullable()->after('name');
            $table->unsignedTinyInteger('age')->nullable()->after('sex');
            $table->string('state', 32)->nullable()->after('age');
            $table->string('municipality', 120)->nullable()->after('state');
            $table->date('event_date')->nullable()->after('municipality');
            $table->json('traits')->nullable()->after('description');
            $table->text('clothing')->nullable()->after('traits');
            $table->text('distinguishing_marks')->nullable()->after('clothing');
            $table->string('institution', 150)->nullable()->after('distinguishing_marks');
            $table->string('contact_phone', 30)->nullable()->after('contact_email');

            $table->index(['status', 'state']);
            $table->index(['status', 'age']);
        });
    }

    public function down(): void
    {
        Schema::table('person_requests', function (Blueprint $table): void {
            $table->dropIndex(['status', 'state']);
            $table->dropIndex(['status', 'age']);
            $table->dropColumn([
                'sex', 'age', 'state', 'municipality', 'event_date', 'traits',
                'clothing', 'distinguishing_marks', 'institution', 'contact_phone',
            ]);
        });
    }
};
