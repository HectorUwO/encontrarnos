<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lo que dice el registro nacional sobre publicar la ficha (SI, NO o SIN
     * DATO). Se guarda aunque la ficha se publique de todos modos, para no
     * perder qué fichas el registro no autoriza y poder volver a ocultarlas.
     */
    public function up(): void
    {
        Schema::table('person_records', function (Blueprint $table): void {
            $table->string('registry_publish', 10)->nullable()->after('published_at');
            $table->index('registry_publish');
        });
    }

    public function down(): void
    {
        Schema::table('person_records', function (Blueprint $table): void {
            $table->dropIndex(['registry_publish']);
            $table->dropColumn('registry_publish');
        });
    }
};
